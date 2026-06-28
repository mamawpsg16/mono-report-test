import pandas as pd
import psycopg2

REQUIRED_COLUMNS = {'customer_code', 'year', 'name'}

EXPECTED_COLUMNS = [
    'customer_code', 'year', 'name', 'email',
    'phone', 'address', 'city', 'country'
]

def process_import(conn, import_id, import_type, file_path):
    try:
        df = read_file(file_path, import_type)
        errors = validate_all_rows(df)

        if errors:
            mark_failed(conn, import_id, errors)
            return

        upsert_all(conn, import_id, df)
        mark_done(conn, import_id, len(df))
        print(f"importer: import #{import_id} done — {len(df)} rows")

    except Exception as e:
        mark_failed(conn, import_id, [f"Unexpected error: {e}"])

def read_file(file_path, import_type):
    if import_type == 'xlsx':
        df = pd.read_excel(file_path, engine='openpyxl', dtype=str)
    else:
        df = pd.read_csv(file_path, dtype=str)

    df.columns = df.columns.str.strip().str.lower().str.replace(' ', '_')
    return df

def validate_all_rows(df):
    errors = []

    missing_cols = REQUIRED_COLUMNS - set(df.columns)
    if missing_cols:
        errors.append(f"Missing required columns: {', '.join(sorted(missing_cols))}")
        return errors

    for i, row in df.iterrows():
        row_num = i + 2
        for col in REQUIRED_COLUMNS:
            val = str(row.get(col, '')).strip()
            if not val or val == 'nan':
                errors.append(f"Row {row_num}: '{col}' is empty")

    return errors

def upsert_all(conn, import_id, df):
    cur = conn.cursor()
    try:
        for col in EXPECTED_COLUMNS:
            if col not in df.columns:
                df[col] = None

        for _, row in df.iterrows():
            cur.execute("""
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
            """, (
                import_id,
                clean(row['customer_code']),
                int(row['year']),
                clean(row['name']),
                clean(row.get('email')),
                clean(row.get('phone')),
                clean(row.get('address')),
                clean(row.get('city')),
                clean(row.get('country')),
            ))

        conn.commit()
    except Exception:
        conn.rollback()
        raise

def mark_done(conn, import_id, total_rows):
    cur = conn.cursor()
    cur.execute(
        "UPDATE imports SET status = 'done', total_rows = %s, processed_rows = %s WHERE id = %s",
        (total_rows, total_rows, import_id)
    )
    conn.commit()

def mark_failed(conn, import_id, errors):
    message = '; '.join(errors[:20])
    cur = conn.cursor()
    cur.execute(
        "UPDATE imports SET status = 'failed', error_message = %s WHERE id = %s",
        (message, import_id)
    )
    conn.commit()
    print(f"importer: import #{import_id} failed — {message}")

def clean(val):
    if val is None or str(val).strip() == '' or str(val) == 'nan':
        return None
    return str(val).strip()
