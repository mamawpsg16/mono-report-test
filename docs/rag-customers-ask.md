# RAG: Ask about your customers

How the "Ask" feature works — a natural-language Q&A layer over the app's own
`customers` data. Built by hand (no LangChain/LlamaIndex), so every step below
is a real, readable function you can open and trace.

---

## 1. The concept, in one paragraph

An LLM only knows what it learned during training — it can't see your private
`customers` table, and if you just asked it "which customers are in Accra?" it
would either refuse or hallucinate an answer. **RAG (Retrieval-Augmented
Generation)** fixes that with two steps: **retrieve** the customer rows most
relevant to the question (via similarity search over precomputed embeddings),
then **generate** an answer by handing those rows to the LLM alongside the
question, so it answers from real data instead of guessing.

Two different models do the two steps, on purpose:

| Step | Model | Where it runs | Needs an API key? |
| --- | --- | --- | --- |
| **Retrieval** (embeddings) | `fastembed` / `BAAI/bge-small-en-v1.5` | Locally, inside `python-service` | No — fully local, free |
| **Generation** (the answer) | Groq (`llama-3.1-8b-instant`) | Groq's cloud | Yes — `GROQ_API_KEY` |

Groq was chosen for generation because it's fast — but **Groq has no
embeddings API**, which is why retrieval uses a separate, local model instead
of also calling Groq for that step.

---

## 2. How a customer row becomes searchable

Nothing is embedded until a customer is uploaded (or re-uploaded). This
happens inside `python-service/customers.py::upsert_customers` — the same
function that writes the `customers` row on confirm:

1. Each row is written to `customers` via `INSERT ... ON CONFLICT DO UPDATE`
   (unchanged from the upload feature), with `RETURNING id`.
2. `_embedding_text(row)` builds a short natural-language blurb per row, e.g.:
   `"Acme Industries, customer code ACME001, year 2025, city Accra, country
   Ghana, email contact@acme.test, phone +233201112223, address 12 Ring Road"`
   — only non-empty fields are included.
3. **All rows in the batch are embedded in one call** (`embed([...])` in
   `embeddings.py`) — fastembed batches internally, so this is much cheaper
   than embedding one row at a time.
4. Each resulting 384-dimension vector is written into `customer_embeddings`
   (`customer_id` → `embedding`), keyed by the same `customer_id`, via its own
   `ON CONFLICT (customer_id) DO UPDATE`.
5. **Steps 1 and 4 happen in the same database transaction** (one `conn.commit()`
   at the very end) — so a customer row and its embedding can never drift out
   of sync; if the request fails partway, neither is written.

**Important consequence:** customers uploaded *before* this feature existed
have no embedding row and won't be found until they're re-uploaded (the
upsert logic makes re-uploading the same file safe — it just refreshes them).

### The `customer_embeddings` table

```
customer_embeddings
├── customer_id   PK, FK -> customers.id, cascade delete
├── embedding     vector(384)   -- pgvector type
├── created_at
└── updated_at
```

- Requires the **pgvector** Postgres extension (`db` image is
  `pgvector/pgvector:pg16`, not plain `postgres:16`).
- Has an **HNSW index** (`vector_cosine_ops`) for fast approximate similarity
  search — added even though the current data volume doesn't strictly need
  one, because it was a deliberate choice to build the real-world pattern
  rather than a toy brute-force search.
- 384 is fastembed's output dimension for `BAAI/bge-small-en-v1.5` — if the
  embedding model ever changes, this column's dimension has to change too.

---

## 3. How a question gets answered — the full request path

```
Browser: AskPanel.vue
  → POST /api/customers/ask   { question, history }
  (Sanctum session cookie — same auth as every other /api/customers* route)

       Laravel: CustomerController::ask()
         validates question (required, string, ≤500 chars) and history
         (array, ≤20 turns, each {role: user|assistant, content ≤2000 chars})
         → CustomerService::ask($question, $history)
              → HTTP POST python-service /customers/ask
                   { question, history }
              ← { answer, sources } | { detail: "..." } on error
       ← Laravel ALWAYS returns HTTP 200 with JSON body --
         success: { answer, sources }
         failure: { answer: null, sources: [], error: "..." }

  ← AskPanel.vue reads `data.error` (show red banner) or
    `data.answer` + `data.sources` (append a new chat bubble)
```

**Inside python-service** (`routers/customers.py::ask` → `rag.py::answer_question`):

1. **Build the retrieval query.** A bare follow-up like *"give me all
   details"* has no name or place in it for similarity search to match
   against. `_retrieval_query()` folds the **previous** user turn's text into
   the **new** question, just for this embedding step — e.g. `"tell me about
   Acme" + "give me all details"` → embeds close to Acme's row. The actual
   conversation sent to the LLM later is untouched by this trick.
2. **Embed that combined text** locally via fastembed.
3. **Similarity search:** `SELECT ... ce.embedding <=> %s::vector AS distance
   ... ORDER BY distance LIMIT 5` — pgvector's `<=>` operator computes cosine
   distance; lower = more similar. Returns the **5 closest rows, always** (see
   §5 — there is no "not similar enough, return nothing" cutoff).
4. **If the table has zero embeddings at all** (nobody uploaded yet, or only
   pre-RAG uploads exist), skip everything else and return a canned "no
   customer data yet" answer — no Groq call is made, so this costs nothing.
5. **Check `GROQ_API_KEY` is set** — if not, raise `RagConfigError` (see §4).
6. **Build the Groq messages:** system prompt (see below) + the **real**
   conversation history, unmodified + a final user message containing the
   retrieved rows formatted as text, followed by the actual question.
7. **Call Groq**, return `{ answer: <model's text>, sources: [{customer_code,
   year, name}, ...] }` — the sources list is always the rows that were
   actually retrieved, so the UI can show *what the answer was based on*
   rather than asking you to trust it blindly.

**The system prompt** (in `rag.py`):
> "You answer questions about a company's customer records. Only use the
> customer records provided below — never invent details that aren't in
> them. If the records don't contain the answer, say you don't know rather
> than guessing."

This is the main defense against hallucination — the model is instructed to
ground itself in the retrieved rows and admit uncertainty rather than invent
data. It's a prompt-level defense, not a hard guarantee.

---

## 4. Error handling — what actually happens at each failure point

| Failure | What happens | What the user sees |
| --- | --- | --- |
| **python-service completely unreachable** (container down, network issue) | Laravel's `Http::post()` throws `ConnectionException`, caught in `CustomerService::ask()` | `"RAG service unreachable"` |
| **`GROQ_API_KEY` missing/empty** | `rag.py` raises `RagConfigError` → router catches it → `HTTPException(500, detail="GROQ_API_KEY is not configured")` → Laravel unmasks the real `detail` message | the literal message `"GROQ_API_KEY is not configured"` (an ops-facing message, not very user-friendly — a known rough edge) |
| **No customers uploaded yet** (or only pre-RAG uploads) | `_search_customers` returns zero rows → handled gracefully, no Groq call made | `"There's no customer data to search yet -- upload some customers first."` (shown as a normal answer, not an error) |
| **Laravel-side validation fails** (question >500 chars, bad history shape) | Laravel's `$request->validate()` throws before ever calling the service — standard 422 | Laravel's generic validation message, via the frontend's catch-all `err.response?.data?.message` |
| **Groq API itself fails** (bad/expired key rejected by Groq, rate limit, the model name gets deprecated, Groq's API is down) | **⚠️ Not caught anywhere.** The `groq` SDK raises an exception that isn't `RagConfigError`, so it propagates unhandled → FastAPI's default 500 handler, which returns **plain text, not JSON** | Laravel's `$response->json('detail')` finds nothing → falls back to the generic `"RAG service error"` — technically works (no crash), but hides the real reason |
| **Postgres/database error** during retrieval or embedding write | Same as above — psycopg exceptions aren't caught in `rag.py`, propagate to a generic 500 | generic `"RAG service error"`, same masking |

**Honest gap, called out on purpose:** the last two rows are the same class of
bug that `validateWithPython`/`processWithPython` already had fixed for them
earlier in this project (a generic message masking the real cause) — but
`rag.py`'s Groq call and DB queries were never wrapped the same way. If Groq
ever rejects a request or the DB hiccups mid-query, you'll see a vague "RAG
service error" instead of the actual reason, and it'll only show up as a real
traceback in `docker compose logs python-service`. Worth fixing the same way
(`try/except` around the Groq call and the DB query, raising a typed error
with a real message) if this becomes a live annoyance — parked, not urgent,
since it fails safely (no crash, no data written wrong), just unhelpfully.

---

## 5. Known simplifications (v1, on purpose)

- **No relevance threshold.** Retrieval always returns the top 5 closest
  rows, even if none are actually relevant to the question — there's no
  "distance too far, return nothing" cutoff. The system prompt is the only
  thing stopping the model from talking about irrelevant customers; it isn't
  a hard filter.
- **Conversation history lives only in the browser tab.** `AskPanel.vue`
  keeps `messages` in local Vue state; nothing is persisted to a database.
  Closing the modal forgets the conversation — capped at 20 turns / 2000
  characters each anyway (enforced both client- and Laravel-side).
- **Retrieval only looks one turn back**, not the whole conversation — a
  three-turn-deep follow-up may lose the thread if it never repeats enough
  context. Works well for the common "tell me about X" → "give me more
  details" pattern it was built to fix.
- **Vectors are bound as plain text, not the `pgvector` Python client's
  wrapper type.** Worth knowing why: the client library's wrapper API turned
  out to differ across installed versions and broke twice during development
  (`Vector` vs `vector`, and a variable-shadowing bug on top of that).
  `embeddings.py::to_pgvector_literal()` instead serializes a vector to
  pgvector's own **text input format** (`"[0.1,0.2,...]"`) and the SQL casts
  it explicitly with `::vector` — that format is a stable property of the
  Postgres extension itself, so it can't break the same way again regardless
  of which client library version is installed.

---

## 6. Where everything lives (quick index)

| Concern | File |
| --- | --- |
| Local embeddings | `python-service/embeddings.py` |
| Retrieval + generation logic | `python-service/rag.py` |
| Embeddings written on upload | `python-service/customers.py::upsert_customers` |
| `/customers/ask` endpoint | `python-service/routers/customers.py` |
| Request schema | `python-service/schemas/customers.py` (`AskRequest`, `AskTurn`) |
| Laravel proxy | `backend/app/Services/CustomerService.php::ask()` |
| Laravel endpoint + validation | `backend/app/Http/Controllers/Api/CustomerController.php::ask()` |
| Route | `backend/routes/api.php` — `POST /customers/ask`, behind `auth:sanctum` |
| `customer_embeddings` schema | `backend/database/migrations/..._create_customer_embeddings_table.php` |
| Chat UI | `frontend/src/views/customers/components/AskPanel.vue` |
| Entry point | floating "Ask" button (bottom-right) on the Customers page |

---

## 7. How to verify it's working

1. `docker compose up --build` (the pgvector image swap needs a rebuild),
   then `docker compose exec backend php artisan migrate`.
2. Upload a small CSV — `SELECT customer_id, embedding IS NOT NULL FROM
   customer_embeddings;` should show a row per uploaded customer.
3. Click the floating Ask button, ask something the data can answer (e.g.
   "which customers are in Accra?") — expect a real answer + a sources list
   naming the right customer.
4. Ask a follow-up like "give me all their details" — should stay on the same
   customer, not lose context.
5. Ask something unrelated, or try it with an empty `customers` table —
   expect a graceful "I don't know" / "no customer data yet", not a crash.
6. Temporarily remove `GROQ_API_KEY` from `.env`, restart python-service, ask
   again — expect the clean config-error message, not a raw 500.
