import re

# Columns that MUST have a value.
REQUIRED = ["customer_code", "year", "name"]

# Simple email shape check. Not perfect, but catches obvious bad ones.
EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")


def validate_rows(rows):
    errors = []   # list of {row, column, message}

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

        # --- rule 2: year must be an integer ---
        year = (row.get("year") or "").strip()
        if year != "" and not year.isdigit():
            errors.append({
                "row": line,
                "column": "year",
                "message": "year must be a whole number",
            })

        # --- rule 3: email shape (only if provided) ---
        email = (row.get("email") or "").strip()
        if email != "" and not EMAIL_RE.match(email):
            errors.append({
                "row": line,
                "column": "email",
                "message": "invalid email address",
            })

    return errors
