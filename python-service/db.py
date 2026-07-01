import os
import psycopg
from psycopg.rows import dict_row          # <-- add this

def get_connection():
    return psycopg.connect(
        host=os.getenv("DB_HOST", "localhost"),
        port=os.getenv("DB_PORT", "5432"),
        dbname=os.getenv("DB_DATABASE", "dataforge"),
        user=os.getenv("DB_USERNAME", "app"),
        password=os.getenv("DB_PASSWORD", "secret"),
        row_factory=dict_row,               # <-- rows come back as dicts
    )
