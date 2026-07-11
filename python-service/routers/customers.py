import logging
import os
import time
from fastapi import APIRouter, HTTPException
from reader import read_customers_file, UnsafeXlsxError
from validator import validate_rows
from customers import compute_diff, upsert_customers
from rag import answer_question, RagConfigError
from schemas.customers import ValidateRequest, ProcessRequest, AskRequest

router = APIRouter(prefix="/customers", tags=["customers"])

logger = logging.getLogger(__name__)

BACKEND_STORAGE_ROOT = os.path.realpath(
    os.getenv("BACKEND_STORAGE_PATH", "../backend/storage/app")
)


def resolve_file(path: str) -> str:
    # os.path.join lets ".." walk outside BACKEND_STORAGE_ROOT; realpath()
    # collapses that, then we confirm the result is still inside the root
    # before ever touching the filesystem for it. Caller controls `path`
    # (it round-trips through the browser), so this can't be skipped.
    full_path = os.path.realpath(os.path.join(BACKEND_STORAGE_ROOT, path))

    if os.path.commonpath([BACKEND_STORAGE_ROOT, full_path]) != BACKEND_STORAGE_ROOT:
        raise HTTPException(status_code=400, detail="Invalid file path")

    if not os.path.isfile(full_path):
        raise HTTPException(status_code=404, detail="File not found")

    return full_path


def read_file_or_400(path: str):
    try:
        return read_customers_file(resolve_file(path))
    except UnsafeXlsxError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.post("/validate")
def validate_file(payload: ValidateRequest):
    rows = read_file_or_400(payload.path)
    errors = validate_rows(rows)
    return compute_diff(rows, errors)


@router.post("/process")
def process_file(payload: ProcessRequest):
    # Phase timings: measure before optimizing. This is the request the user
    # sits and waits on (Laravel calls it synchronously, 120s timeout), so we
    # keep a permanent log line showing where each second went.
    started = time.perf_counter()
    rows = read_file_or_400(payload.path)
    read_done = time.perf_counter()

    errors = validate_rows(rows)
    validate_done = time.perf_counter()
    if errors:
        return {"status": "failed", "processed_rows": 0, "errors": errors}

    upsert_customers(rows, payload.original_filename, payload.user_id)
    finished = time.perf_counter()

    logger.info(
        "process: rows=%d read=%.3fs validate=%.3fs upsert=%.3fs total=%.3fs",
        len(rows),
        read_done - started,
        validate_done - read_done,
        finished - validate_done,
        finished - started,
    )
    return {"status": "done", "processed_rows": len(rows), "errors": []}


@router.post("/ask")
def ask(payload: AskRequest):
    try:
        history = [turn.model_dump() for turn in payload.history]
        return answer_question(payload.question, history)
    except RagConfigError as e:
        raise HTTPException(status_code=500, detail=str(e))
