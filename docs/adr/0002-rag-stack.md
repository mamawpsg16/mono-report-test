# 0002 — RAG stack: fastembed + pgvector + Groq, hand-rolled

## Context

An LLM can't see the app's private `customers` data — asked directly, it
would refuse or hallucinate. Adding a natural-language "Ask" feature over
`customers` requires a RAG (Retrieval-Augmented Generation) pipeline:
retrieve the relevant rows via similarity search, then generate an answer
grounded in them. This requires four concrete choices: an embedding model
(retrieval), a vector similarity store, a generation LLM, and whether to
hand-roll the pipeline or use a framework (LangChain/LlamaIndex).

## Problem

Which embedding model, which vector store, which generation LLM, and
hand-rolled vs framework — given this project's constraints: Postgres
already exists (no new datastore infra wanted lightly), the project is a
learning vehicle (visibility into every step matters more than
convenience), and there's no existing paid LLM API commitment yet.

## Options

1. **Embeddings** —
   - `fastembed` (local, ONNX runtime, no API key) — free, no network
     dependency for retrieval.
   - OpenAI/Cohere embeddings API — potentially better quality, but a
     second paid API and a second point of failure for a step that local
     models handle well at this data scale.
   - `sentence-transformers` directly — same idea as fastembed but pulls
     in PyTorch; fastembed exists specifically to avoid that weight.
2. **Vector store** —
   - `pgvector` — reuses the Postgres already running.
   - Dedicated vector DB (Pinecone/Weaviate/Chroma) — better at massive
     scale, but a new service to deploy/secure/back up, unjustified here.
   - Brute-force cosine similarity in Python — works at tiny scale, but
     reinvents what pgvector already does correctly.
3. **Generation LLM** —
   - Groq — very fast inference, generous free tier, but no embeddings
     endpoint (why embeddings needed a separate provider).
   - OpenAI — has both embeddings and chat in one API, but slower and
     costs money from turn one.
   - Local LLM (Ollama etc.) — free, but generation quality at runnable
     parameter sizes is noticeably worse for a feature meant to read as
     trustworthy.
4. **Hand-rolled vs framework** —
   - Hand-rolled (`rag.py`, `embeddings.py`) — every step is a readable,
     traceable function.
   - LangChain/LlamaIndex — convenient, but hides real complexity behind
     abstractions that are hard to debug when they misbehave — the wrong
     tradeoff for a project whose point is understanding the mechanics.

## Decision

- **`fastembed`** (`BAAI/bge-small-en-v1.5`, 384-dim) for embeddings —
  local, no second API key, sufficient quality at this data volume.
- **`pgvector`** for similarity search — reuses existing Postgres
  (`pgvector/pgvector:pg16` image), HNSW index for approximate search.
- **Groq** (`llama-3.1-8b-instant`) for generation — fast, and the
  local/cloud split (embed locally, generate via API) is a standard,
  well-understood RAG pattern, not an awkward compromise.
- **Hand-rolled**, no LangChain/LlamaIndex — matches the project's actual
  goal (learning the real mechanics), and keeps every step debuggable.
- Vectors are bound to SQL via pgvector's plain-text literal format
  (`"[0.1,0.2,...]"` cast with `::vector`), not the `pgvector` Python
  client's wrapper type — that wrapper's API differed across installed
  versions and broke twice during development; the text format is a
  stable property of the Postgres extension itself.

## Consequences

- Two different providers for the two RAG steps: `GROQ_API_KEY` is
  required for generation; embeddings need no key at all.
- `db`'s image changed from `postgres:16` to `pgvector/pgvector:pg16` —
  an infra change, not just an app-level dependency.
- No relevance-threshold cutoff in v1 — retrieval always returns the top 5
  closest rows regardless of actual relevance; the system prompt is the
  only thing stopping the model from discussing irrelevant customers.
- 384 is fixed to `BAAI/bge-small-en-v1.5`'s output dimension — changing
  the embedding model later means changing `customer_embeddings.embedding`
  and re-embedding every row.
- Hand-rolling means the RAG code (retrieval query building, prompt
  construction, error handling) is maintained by hand, with no framework
  upgrades doing that work — an accepted cost for the visibility gained.
- Known gap: `rag.py`'s Groq call and DB queries aren't wrapped the same
  way `CustomerService`'s python-service calls are (`try/except` +
  `.failed()` + `json('detail')`) — an unhandled Groq/DB exception
  currently surfaces as a generic `"RAG service error"` instead of the
  real cause. Parked, not urgent (fails safely, just unhelpfully) — see
  `docs/rag-customers-ask.md` §4.

## Revisit when

- Retrieved-but-irrelevant answers become a real problem — add a distance
  threshold cutoff instead of always returning top 5.
- Groq deprecates the model, changes pricing unfavorably, or an outage
  makes fast-but-single-vendor generation a liability — evaluate a
  fallback provider or self-hosted generation.
- The embedding model changes — plan a migration for
  `customer_embeddings.embedding`'s dimension and re-embed all rows.
- The unhandled-exception gap above becomes a live annoyance — wrap the
  Groq call and DB queries the same way `CustomerService` already does.
