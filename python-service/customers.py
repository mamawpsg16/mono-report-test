from db import get_connection

# Fields compared when deciding whether an existing customer row would change.
COMPARE_FIELDS = ["name", "email", "phone", "address", "city", "country"]


def _clean(value):
    value = (value or "").strip()
    return value or None


def compute_diff(rows, errors=None):
    """Diff incoming rows against existing customers. Returns summary + tagged rows list."""

    if errors is None:
        errors = []

    pairs = [(row.get("customer_code", "").strip(), int(row["year"])) for row in rows]

    if not pairs:
        return {
            "summary": {"total_rows": 0, "new_count": 0, "update_count": 0, "error_count": len(errors)},
            "rows": [],
            "errors": errors,
        }

    # one batched query instead of N queries
    placeholders = ",".join(["(%s, %s)"] * len(pairs))
    flat_params = [v for pair in pairs for v in pair]

    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT customer_code, year, name, email, phone, address, city, country "
                f"FROM customers WHERE (customer_code, year) IN ({placeholders})",
                flat_params,
            )
            existing = {(r["customer_code"], r["year"]): r for r in cur.fetchall()}

    tagged_rows = []
    new_count = 0
    update_count = 0

    for index, row in enumerate(rows):
        code = row.get("customer_code", "").strip()
        year = int(row["year"])
        current = existing.get((code, year))

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


def upsert_customers(rows, original_filename, user_id=None):
    """Write every row into customers. New (code, year) -> INSERT. Existing -> UPDATE."""
    with get_connection() as conn:
        with conn.cursor() as cur:
            for row in rows:
                cur.execute(
                    """
                    INSERT INTO customers
                        (original_filename, customer_code, year, name, email, phone, address, city, country,
                         created_by, updated_by, created_at, updated_at)
                    VALUES
                        (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON CONFLICT (customer_code, year) DO UPDATE SET
                        original_filename = EXCLUDED.original_filename,
                        name = EXCLUDED.name,
                        email = EXCLUDED.email,
                        phone = EXCLUDED.phone,
                        address = EXCLUDED.address,
                        city = EXCLUDED.city,
                        country = EXCLUDED.country,
                        is_active = true,
                        updated_by = EXCLUDED.updated_by,
                        updated_at = NOW()
                    """,
                    (
                        original_filename,
                        row.get("customer_code", "").strip(),
                        int(row["year"]),
                        row.get("name", "").strip(),
                        _clean(row.get("email")),
                        _clean(row.get("phone")),
                        _clean(row.get("address")),
                        _clean(row.get("city")),
                        _clean(row.get("country")),
                        user_id,
                        user_id,
                    ),
                )
        conn.commit()   # one commit after all rows -> all-or-nothing per request
