from pydantic import BaseModel


class ValidateRequest(BaseModel):
    path: str   # stored_path relative to BACKEND_STORAGE_PATH, e.g. "uploads/uuid.csv"


class ProcessRequest(BaseModel):
    path: str
    original_filename: str
    user_id: int | None = None


class AskRequest(BaseModel):
    question: str
