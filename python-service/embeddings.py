from fastembed import TextEmbedding

# Loaded once at import time -- fastembed pulls the model to disk on first
# use and keeps it in memory after, so every request after the first is fast.
# 384-dim output; must match the `vector(384)` column in customer_embeddings
# (backend/database/migrations/..._create_customer_embeddings_table.php).
MODEL_NAME = "BAAI/bge-small-en-v1.5"
_model = TextEmbedding(model_name=MODEL_NAME)


def embed(texts: list[str]) -> list[list[float]]:
    """Embed a batch of texts. Returns one 384-dim vector per input text,
    in the same order."""
    return [vector.tolist() for vector in _model.embed(texts)]


def to_pgvector_literal(values: list[float]) -> str:
    """Serialize to pgvector's text input format, e.g. "[0.1,0.2,0.3]".
    Bind this as a plain string param and cast with `%s::vector` in SQL --
    this format is a stable property of the Postgres extension itself, so it
    doesn't depend on which pgvector-python client version is installed."""
    return "[" + ",".join(str(v) for v in values) + "]"
