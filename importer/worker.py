import os
import time
import psycopg2
from parse_csv import process_import

def get_connection():
    dsn = (
        f"host={os.environ['DB_HOST']} "
        f"port={os.environ['DB_PORT']} "
        f"dbname={os.environ['DB_DATABASE']} "
        f"user={os.environ['DB_USERNAME']} "
        f"password={os.environ['DB_PASSWORD']}"
    )
    return psycopg2.connect(dsn)

def wait_for_postgres():
    print("importer: waiting for Postgres...")
    while True:
        try:
            conn = get_connection()
            conn.close()
            print("importer: connected to Postgres")
            return
        except psycopg2.OperationalError:
            time.sleep(1)

def poll_for_jobs():
    poll_interval = int(os.environ.get("IMPORT_POLL_SECONDS", 3))
    import_dir = os.environ.get("IMPORT_DIR", "/data/imports")

    print(f"importer: polling every {poll_interval}s")
    while True:
        try:
            conn = get_connection()
            conn.autocommit = False
            cur = conn.cursor()

            cur.execute(
                "SELECT id, type, stored_path FROM imports "
                "WHERE status = 'pending' "
                "ORDER BY created_at ASC "
                "LIMIT 1 "
                "FOR UPDATE SKIP LOCKED"
            )
            row = cur.fetchone()

            if row:
                import_id, import_type, stored_path = row
                filename = os.path.basename(stored_path)
                file_path = os.path.join(import_dir, filename)

                print(f"importer: picked up import #{import_id} ({filename})")

                cur.execute(
                    "UPDATE imports SET status = 'processing' WHERE id = %s",
                    (import_id,)
                )
                conn.commit()

                process_import(conn, import_id, import_type, file_path)
            else:
                conn.close()

        except Exception as e:
            print(f"importer: error in poll loop: {e}")
            try:
                conn.close()
            except:
                pass

        time.sleep(poll_interval)

def main():
    wait_for_postgres()
    poll_for_jobs()

if __name__ == "__main__":
    main()
