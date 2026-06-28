import sys
import os
import json

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', 'importer'))

import psycopg2
from parse_csv import process_import


def main():
    if len(sys.argv) < 6:
        print(json.dumps({"error": "Usage: run_import.py <file_path> <import_id> <db_host> <db_port> <db_name> <db_user> <db_pass>"}))
        sys.exit(1)

    file_path = sys.argv[1]
    import_id = int(sys.argv[2])
    db_host = sys.argv[3]
    db_port = sys.argv[4]
    db_name = sys.argv[5]
    db_user = sys.argv[6] if len(sys.argv) > 6 else "app"
    db_pass = sys.argv[7] if len(sys.argv) > 7 else "secret"

    try:
        conn = psycopg2.connect(
            host=db_host, port=db_port,
            dbname=db_name, user=db_user, password=db_pass
        )

        cur = conn.cursor()
        cur.execute("SELECT type FROM imports WHERE id = %s", (import_id,))
        row = cur.fetchone()
        if not row:
            print(json.dumps({"error": f"Import #{import_id} not found"}))
            sys.exit(1)

        import_type = row[0]
        process_import(conn, import_id, import_type, file_path)

        cur = conn.cursor()
        cur.execute("SELECT status, total_rows, processed_rows, error_message FROM imports WHERE id = %s", (import_id,))
        result = cur.fetchone()
        conn.close()

        print(json.dumps({
            "status": result[0],
            "total_rows": result[1],
            "processed_rows": result[2],
            "error": result[3],
        }))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
