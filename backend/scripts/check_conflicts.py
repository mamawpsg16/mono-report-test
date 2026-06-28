import sys
import os
import json

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', 'importer'))

import psycopg2
from parse_csv import read_file, validate_all_rows, REQUIRED_COLUMNS


def main():
    if len(sys.argv) < 6:
        print(json.dumps({"error": "Usage: check_conflicts.py <file_path> <file_type> <db_host> <db_port> <db_name> [db_user] [db_pass]"}))
        sys.exit(1)

    file_path = sys.argv[1]
    file_type = sys.argv[2]
    db_host = sys.argv[3]
    db_port = sys.argv[4]
    db_name = sys.argv[5]
    db_user = sys.argv[6] if len(sys.argv) > 6 else "app"
    db_pass = sys.argv[7] if len(sys.argv) > 7 else "secret"

    try:
        df = read_file(file_path, file_type)

        errors = validate_all_rows(df)
        if errors:
            print(json.dumps({"error": "; ".join(errors[:20])}))
            sys.exit(1)

        conn = psycopg2.connect(
            host=db_host, port=db_port,
            dbname=db_name, user=db_user, password=db_pass
        )
        cur = conn.cursor()

        pairs = []
        for _, row in df.iterrows():
            code = str(row.get("customer_code", "")).strip()
            year = int(row.get("year", 0))
            pairs.append((code, year))

        if not pairs:
            print(json.dumps({"new_rows": 0, "update_rows": 0, "updates": []}))
            return

        placeholders = ",".join(["(%s, %s)"] * len(pairs))
        flat_params = [v for pair in pairs for v in pair]

        cur.execute(
            f"SELECT customer_code, year, name, email, phone, address, city, country "
            f"FROM customers WHERE (customer_code, year) IN ({placeholders})",
            flat_params
        )

        existing = {}
        for r in cur.fetchall():
            existing[(r[0], r[1])] = {
                "name": r[2], "email": r[3], "phone": r[4],
                "address": r[5], "city": r[6], "country": r[7],
            }

        conn.close()

        updates = []
        new_count = 0

        for i, (_, row) in enumerate(df.iterrows()):
            code = str(row.get("customer_code", "")).strip()
            year = int(row.get("year", 0))
            key = (code, year)

            if key in existing:
                current = existing[key]
                new_data = {
                    "name": str(row.get("name", "")).strip() or None,
                    "email": str(row.get("email", "")).strip() or None,
                    "phone": str(row.get("phone", "")).strip() or None,
                    "address": str(row.get("address", "")).strip() or None,
                    "city": str(row.get("city", "")).strip() or None,
                    "country": str(row.get("country", "")).strip() or None,
                }

                changes = {}
                for field in ["name", "email", "phone", "address", "city", "country"]:
                    old_val = current.get(field) or ""
                    new_val = new_data.get(field) or ""
                    if old_val != new_val:
                        changes[field] = {"from": old_val, "to": new_val}

                updates.append({
                    "row": i + 2,
                    "customer_code": code,
                    "year": year,
                    "changes": changes,
                })
            else:
                new_count += 1

        print(json.dumps({
            "new_rows": new_count,
            "update_rows": len(updates),
            "updates": updates,
        }))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
