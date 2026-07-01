from db import get_connection

# Fields compared when deciding whether an existing customer row would change.
COMPARE_FIELDS = ["name", "email", "phone", "address", "city", "country"]


def _clean(value):
    value = (value or "").strip()
    return value or None


def compute_conflicts(rows):
    """Compare incoming rows against existing customers, before writing anything."""

    # identity = (customer_code, year) pair, matches DB unique constraint
    pairs = [(row.get("customer_code", "").strip(), int(row["year"])) for row in rows]

    if not pairs:
        return {"new_rows": 0, "update_rows": 0, "updates": []}

    # one batched query instead of N queries (100k rows -> 1 round-trip, not 100k)
    placeholders = ",".join(["(%s, %s)"] * len(pairs))
    flat_params = [v for pair in pairs for v in pair]   # flatten [(a,1),(b,2)] -> [a,1,b,2]

    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT customer_code, year, name, email, phone, address, city, country "
                f"FROM customers WHERE (customer_code, year) IN ({placeholders})",
                flat_params,
            )
            # dict keyed by (code, year) -> O(1) lookup per row below, instead of scanning a list
            existing = {(r["customer_code"], r["year"]): r for r in cur.fetchall()}

    updates = []
    new_count = 0

    for index, row in enumerate(rows):
        line = index + 2   # +2: header is csv line 1, data starts line 2
        code = row.get("customer_code", "").strip()
        year = int(row["year"])
        current = existing.get((code, year))

        if current is None:
            new_count += 1   # no match in DB -> this row is a brand-new customer
            continue

        # row exists -> diff field by field, only record what actually changed
        changes = {}
        for field in COMPARE_FIELDS:
            old_val = current.get(field) or ""
            new_val = _clean(row.get(field)) or ""
            if old_val != new_val:
                changes[field] = {"from": old_val, "to": new_val}

        updates.append({
            "row": line,
            "customer_code": code,
            "year": year,
            "changes": changes,
        })

    return {"new_rows": new_count, "update_rows": len(updates), "updates": updates}


def upsert_customers(import_id, rows):
    """Write every row into customers. New (code, year) -> INSERT. Existing -> UPDATE."""
    with get_connection() as conn:
        with conn.cursor() as cur:
            for row in rows:
                cur.execute(
                    """
                    INSERT INTO customers
                        (import_id, customer_code, year, name, email, phone, address, city, country, created_at, updated_at)
                    VALUES
                        (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON CONFLICT (customer_code, year) DO UPDATE SET
                        import_id = EXCLUDED.import_id,
                        name = EXCLUDED.name,
                        email = EXCLUDED.email,
                        phone = EXCLUDED.phone,
                        address = EXCLUDED.address,
                        city = EXCLUDED.city,
                        country = EXCLUDED.country,
                        is_active = true,
                        updated_at = NOW()
                    """,
                    (
                        import_id,
                        row.get("customer_code", "").strip(),
                        int(row["year"]),
                        row.get("name", "").strip(),
                        _clean(row.get("email")),
                        _clean(row.get("phone")),
                        _clean(row.get("address")),
                        _clean(row.get("city")),
                        _clean(row.get("country")),
                    ),
                )
        conn.commit()   # one commit after all rows -> all-or-nothing per request


def mark_done(import_id, total_rows):
    """Flip imports.status to done, record row counts."""
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE imports SET status = 'done', total_rows = %s, processed_rows = %s WHERE id = %s",
                (total_rows, total_rows, import_id),
            )
        conn.commit()


def mark_failed(import_id, message):
    """Flip imports.status to failed, store why (first N errors, joined)."""
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE imports SET status = 'failed', error_message = %s WHERE id = %s",
                (message, import_id),
            )
        conn.commit()
