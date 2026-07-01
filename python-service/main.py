import os
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from db import get_connection
from reader import read_csv
from validator import validate_rows
from customers import compute_conflicts, upsert_customers, mark_done, mark_failed

app = FastAPI(title="Importer Service")

# Laravel stores uploads under backend/storage/app/<stored_path>.
# python-service is a sibling folder to backend/, so resolve relative to that
# unless overridden (e.g. inside Docker where paths differ).
BACKEND_STORAGE_PATH = os.getenv("BACKEND_STORAGE_PATH", "../backend/storage/app")


def get_import_or_404(import_id: int) -> dict:
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, type, status, original_filename, stored_path, processed_rows "
                "FROM imports WHERE id = %s",
                (import_id,),
            )
            row = cur.fetchone()

    if row is None:
        raise HTTPException(status_code=404, detail="Import not found")

    return row


def resolve_import_file(import_row: dict) -> str:
    path = os.path.join(BACKEND_STORAGE_PATH, import_row["stored_path"])

    if not os.path.isfile(path):
        raise HTTPException(status_code=404, detail=f"Import file not found on disk: {path}")

    return path

# Shape of the JSON Laravel will POST to trigger an import.
class ImportRequest(BaseModel):
    import_id: int          # which row in the imports table
    type: str               # "csv" or "xlsx"
    stored_path: str        # where the uploaded file lives


@app.get("/")
def read_root():
    return {"service": "importer", "status": "alive"}

@app.get("/imports/{import_id}")
def get_import(import_id: int):
    return get_import_or_404(import_id)


@app.get("/imports/{import_id}/preview")
def preview_import(import_id: int):
    import_row = get_import_or_404(import_id)
    file_path = resolve_import_file(import_row)
    rows = read_csv(file_path)
    return {
        "import_id": import_id,
        "total_rows": len(rows),     # how many data rows
        "columns": list(rows[0].keys()) if rows else [],   # header names
        "preview": rows[:3],         # first 3 rows only
    }


@app.post("/imports")
def create_import(payload: ImportRequest):
    # `payload` is already validated + typed. Access fields with dot.
    return {
        "received": True,
        "import_id": payload.import_id,
        "type": payload.type,
        "stored_path": payload.stored_path,
    }

@app.get("/imports/{import_id}/validate")
def validate_import(import_id: int):
    import_row = get_import_or_404(import_id)
    file_path = resolve_import_file(import_row)
    rows = read_csv(file_path)
    errors = validate_rows(rows)
    return {
        "import_id": import_id,
        "total_rows": len(rows),
        "error_count": len(errors),
        "errors": errors,
    }


def _format_errors(errors, limit=20):
    # collapse validator's [{row,column,message}, ...] into one string,
    # same shape ImportService.php expects in conflicts['error']
    parts = [f"Row {e['row']}: {e['column']} - {e['message']}" for e in errors[:limit]]
    return "; ".join(parts)


@app.get("/imports/{import_id}/conflicts")
def conflicts_import(import_id: int):
    import_row = get_import_or_404(import_id)
    file_path = resolve_import_file(import_row)
    rows = read_csv(file_path)

    errors = validate_rows(rows)
    if errors:
        return {"error": _format_errors(errors)}

    return compute_conflicts(rows)


@app.post("/imports/{import_id}/process")
def process_import(import_id: int):
    import_row = get_import_or_404(import_id)
    file_path = resolve_import_file(import_row)
    rows = read_csv(file_path)

    errors = validate_rows(rows)
    if errors:
        message = _format_errors(errors)
        mark_failed(import_id, message)
        return {"status": "failed", "total_rows": len(rows), "processed_rows": 0, "error": message}

    upsert_customers(import_id, rows)
    mark_done(import_id, len(rows))
    return {"status": "done", "total_rows": len(rows), "processed_rows": len(rows), "error": None}
