import re

# Columns that MUST have a value.
REQUIRED = ["customer_code", "year", "email", "name"]

# Simple email shape check. Not perfect, but catches obvious bad ones.
EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")


def validate_rows(rows):
    errors = []   # list of {row, column, message}

    # customer_code must be unique WITHIN the file too: the batched upsert
    # writes all rows in one statement, and Postgres refuses to update the
    # same row twice in one statement ("ON CONFLICT DO UPDATE cannot affect
    # row a second time"). Before this rule, duplicates silently last-won --
    # rejecting loudly beats cleaning silently.
    first_seen_line = {}   # customer_code -> line it first appeared on

    for index, row in enumerate(rows):
        line = index + 2          # +2 -> header is line 1, data starts line 2

        # --- rule 1: required fields not empty ---
        for col in REQUIRED:
            value = (row.get(col) or "").strip()
            if value == "":
                errors.append({
                    "row": line,
                    "column": col,
                    "message": f"{col} is required",
                })

        # --- rule 2: no duplicate customer_code within the file ---
        code = (row.get("customer_code") or "").strip()
        if code != "":
            if code in first_seen_line:
                errors.append({
                    "row": line,
                    "column": "customer_code",
                    "message": f"duplicate customer_code (first seen on row {first_seen_line[code]})",
                })
            else:
                first_seen_line[code] = line

        # --- rule 3: year must be an integer ---
        year = (row.get("year") or "").strip()
        if year != "" and not year.isdigit():
            errors.append({
                "row": line,
                "column": "year",
                "message": "year must be a whole number",
            })

        # --- rule 4: email shape (only if provided) ---
        email = (row.get("email") or "").strip()
        if email != "" and not EMAIL_RE.match(email):
            errors.append({
                "row": line,
                "column": "email",
                "message": "invalid email address",
            })

    return errors
