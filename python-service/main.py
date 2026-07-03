from fastapi import FastAPI
from routers.customers import router as customers_router

app = FastAPI(title="Importer Service")

app.include_router(customers_router)


@app.get("/")
def read_root():
    return {"service": "importer", "status": "alive"}
