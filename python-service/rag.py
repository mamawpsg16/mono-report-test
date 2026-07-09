import os
from groq import Groq
from db import get_connection
from embeddings import embed, to_pgvector_literal

# Fast Groq-hosted model -- picked for low latency, not quality. Groq's
# model catalog changes over time; if this 404s, check console.groq.com/docs
# for the current fast/instant tier and swap the name here.
GROQ_MODEL = "llama-3.1-8b-instant"

SYSTEM_PROMPT = (
    "You answer questions about a company's customer records. "
    "Only use the customer records provided below -- never invent details "
    "that aren't in them. If the records don't contain the answer, say you "
    "don't know rather than guessing."
)


class RagConfigError(RuntimeError):
    pass


def _search_customers(question: str, top_k: int):
    [query_vector] = embed([question])

    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT c.customer_code, c.year, c.name, c.email, c.phone,
                       c.address, c.city, c.country,
                       ce.embedding <=> %s::vector AS distance
                FROM customer_embeddings ce
                JOIN customers c ON c.id = ce.customer_id
                ORDER BY distance
                LIMIT %s
                """,
                # bind as a plain string in pgvector's text format ("[0.1,...]")
                # and cast in SQL -- a bare Python list here gets sent as
                # double precision[], which has no <=> operator against
                # `vector` (that's what "operator does not exist: vector <=>
                # double precision[]" meant); the pgvector-python client's
                # own wrapper type varies by version, so don't depend on it
                (to_pgvector_literal(query_vector), top_k),
            )
            return cur.fetchall()


def _format_context(matches: list[dict]) -> str:
    lines = []
    for m in matches:
        lines.append(
            f"- {m['name']} (code {m['customer_code']}, year {m['year']}): "
            f"email {m['email'] or 'n/a'}, phone {m['phone'] or 'n/a'}, "
            f"address {m['address'] or 'n/a'}, city {m['city'] or 'n/a'}, "
            f"country {m['country'] or 'n/a'}"
        )
    return "\n".join(lines)


def answer_question(question: str, top_k: int = 5) -> dict:
    matches = _search_customers(question, top_k)

    if not matches:
        return {
            "answer": "There's no customer data to search yet -- upload some customers first.",
            "sources": [],
        }

    api_key = os.getenv("GROQ_API_KEY")
    if not api_key:
        raise RagConfigError("GROQ_API_KEY is not configured")

    client = Groq(api_key=api_key)
    completion = client.chat.completions.create(
        model=GROQ_MODEL,
        messages=[
            {"role": "system", "content": SYSTEM_PROMPT},
            {
                "role": "user",
                "content": (
                    f"Customer records:\n{_format_context(matches)}\n\n"
                    f"Question: {question}"
                ),
            },
        ],
    )

    return {
        "answer": completion.choices[0].message.content,
        "sources": [
            {
                "customer_code": m["customer_code"],
                "year": m["year"],
                "name": m["name"],
            }
            for m in matches
        ],
    }
