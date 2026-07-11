import logging
import time

from db import get_connection
from embeddings import embed, to_pgvector_literal

logger = logging.getLogger(__name__)

# Fields compared when deciding whether an existing customer row would change.
COMPARE_FIELDS = ["name", "email", "phone", "address", "city", "country"]


def _clean(value):
    value = (value or "").strip()
    return value or None


def _embedding_text(row):
    """Short natural-language blurb of a row, for the RAG similarity search
    (see rag.py). Only non-empty fields are included."""
    parts = [
        row.get("name", "").strip(),
        f"customer code {row.get('customer_code', '').strip()}",
        f"year {row.get('year', '')}",
    ]
    for label, key in [
        ("city", "city"), ("country", "country"),
        ("email", "email"), ("phone", "phone"), ("address", "address"),
    ]:
        value = _clean(row.get(key))
        if value:
            parts.append(f"{label} {value}")
    return ", ".join(parts)


def compute_diff(rows, errors=None):
    """Diff incoming rows against existing customers. Returns summary + tagged rows list."""

    if errors is None:
        errors = []

    codes = [row.get("customer_code", "").strip() for row in rows]

    if not codes:
        return {
            "summary": {"total_rows": 0, "new_count": 0, "update_count": 0, "error_count": len(errors)},
            "rows": [],
            "errors": errors,
        }

    # one customer = one company: match on customer_code alone (year is now just
    # an attribute). One batched query instead of N.
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT customer_code, name, email, phone, address, city, country "
                "FROM customers WHERE customer_code = ANY(%s)",
                (codes,),
            )
            existing = {r["customer_code"]: r for r in cur.fetchall()}

    tagged_rows = []
    new_count = 0
    update_count = 0

    for row in rows:
        code = row.get("customer_code", "").strip()
        current = existing.get(code)

        if current is None:
            new_count += 1
            tagged_rows.append({**row, "_status": "new"})
        else:
            update_count += 1
            changes = {}
            for field in COMPARE_FIELDS:
                old_val = current.get(field) or ""
                new_val = _clean(row.get(field)) or ""
                if old_val != new_val:
                    changes[field] = {"from": old_val, "to": new_val}
            tagged_rows.append({**row, "_status": "update", "_changes": changes})

    return {
        "summary": {
            "total_rows": len(rows),
            "new_count": new_count,
            "update_count": update_count,
            "error_count": len(errors),
        },
        "rows": tagged_rows,
        "errors": errors,
    }


def _needs_embedding(current, row):
    """True when a row's embedding must be (re)computed: the customer is new,
    has no stored embedding, or a field that appears in the embedding text
    changed. Skipping the rest is the big win -- embed() measured at ~13s of
    a 13.9s 1k-row request, and re-uploads are mostly unchanged rows."""
    if current is None or not current["has_embedding"]:
        return True
    if int(row["year"]) != current["year"]:
        return True
    for field in COMPARE_FIELDS:
        if (_clean(row.get(field)) or "") != (current.get(field) or ""):
            return True
    return False


def upsert_customers(rows, original_filename, user_id=None):
    """Write every row into customers in ONE statement: new customer_code ->
    INSERT, existing -> UPDATE. Embeddings for new/changed rows are computed
    and written in the same transaction, so a customer row and its embedding
    never drift out of sync."""
    codes = [row.get("customer_code", "").strip() for row in rows]

    started = time.perf_counter()
    with get_connection() as conn:
        with conn.cursor() as cur:
            # Snapshot what's stored NOW (before the upsert overwrites it) to
            # decide which rows really need re-embedding. The LEFT JOIN also
            # catches a customer whose embedding row is missing entirely.
            cur.execute(
                """
                SELECT c.customer_code, c.year, c.name, c.email, c.phone,
                       c.address, c.city, c.country,
                       (e.customer_id IS NOT NULL) AS has_embedding
                FROM customers c
                LEFT JOIN customer_embeddings e ON e.customer_id = c.id
                WHERE c.customer_code = ANY(%s)
                """,
                (codes,),
            )
            existing = {r["customer_code"]: r for r in cur.fetchall()}

            # unnest() turns one array per column into rows server-side, so
            # the whole file costs ONE network round trip instead of one per
            # row. Precondition: no duplicate customer_code in the file
            # (validator.py rejects them) -- Postgres refuses to ON CONFLICT-
            # update the same row twice within a single statement.
            cur.execute(
                """
                INSERT INTO customers
                    (original_filename, customer_code, year, name, email, phone,
                     address, city, country, created_by, updated_by, created_at, updated_at)
                SELECT
                    %(original_filename)s, u.customer_code, u.year, u.name, u.email,
                    u.phone, u.address, u.city, u.country,
                    %(user_id)s, %(user_id)s, NOW(), NOW()
                FROM unnest(
                    %(codes)s::text[], %(years)s::int[], %(names)s::text[],
                    %(emails)s::text[], %(phones)s::text[], %(addresses)s::text[],
                    %(cities)s::text[], %(countries)s::text[]
                ) AS u(customer_code, year, name, email, phone, address, city, country)
                ON CONFLICT (customer_code) DO UPDATE SET
                    original_filename = EXCLUDED.original_filename,
                    year = EXCLUDED.year,
                    name = EXCLUDED.name,
                    email = EXCLUDED.email,
                    phone = EXCLUDED.phone,
                    address = EXCLUDED.address,
                    city = EXCLUDED.city,
                    country = EXCLUDED.country,
                    is_active = true,
                    updated_by = EXCLUDED.updated_by,
                    updated_at = NOW()
                RETURNING id, customer_code
                """,
                {
                    "original_filename": original_filename,
                    "user_id": user_id,
                    "codes": codes,
                    "years": [int(row["year"]) for row in rows],
                    "names": [row.get("name", "").strip() for row in rows],
                    "emails": [_clean(row.get("email")) for row in rows],
                    "phones": [_clean(row.get("phone")) for row in rows],
                    "addresses": [_clean(row.get("address")) for row in rows],
                    "cities": [_clean(row.get("city")) for row in rows],
                    "countries": [_clean(row.get("country")) for row in rows],
                },
            )
            id_by_code = {r["customer_code"]: r["id"] for r in cur.fetchall()}
            customers_done = time.perf_counter()

            rows_to_embed = [
                (id_by_code[code], row)
                for code, row in zip(codes, rows)
                if _needs_embedding(existing.get(code), row)
            ]
            vectors = embed([_embedding_text(row) for _, row in rows_to_embed]) if rows_to_embed else []
            embed_done = time.perf_counter()

            # executemany batches these through psycopg's pipeline mode --
            # effectively one round trip, no RETURNING needed here.
            cur.executemany(
                """
                INSERT INTO customer_embeddings (customer_id, embedding, created_at, updated_at)
                VALUES (%s, %s::vector, NOW(), NOW())
                ON CONFLICT (customer_id) DO UPDATE SET
                    embedding = EXCLUDED.embedding,
                    updated_at = NOW()
                """,
                # see rag.py -- bind pgvector's text format + cast in SQL
                # rather than depending on the client library's wrapper type
                [
                    (customer_id, to_pgvector_literal(vector))
                    for (customer_id, _), vector in zip(rows_to_embed, vectors)
                ],
            )
            embeddings_done = time.perf_counter()
        conn.commit()   # one commit after all rows -> all-or-nothing per request

    logger.info(
        "upsert: rows=%d embedded=%d customers=%.3fs embed=%.3fs embeddings=%.3fs",
        len(rows),
        len(rows_to_embed),
        customers_done - started,
        embed_done - customers_done,
        embeddings_done - embed_done,
    )
