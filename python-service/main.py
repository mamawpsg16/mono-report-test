import logging

from fastapi import FastAPI
from routers.customers import router as customers_router

# uvicorn only wires up its OWN loggers ("uvicorn", "uvicorn.access") -- our
# module loggers would fall through to a silent root logger. basicConfig gives
# the root logger a stderr handler so our INFO lines (e.g. the /process phase
# timings) actually show up in `docker compose logs python-service`.
logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")

app = FastAPI(title="Importer Service")

app.include_router(customers_router)


@app.get("/")
def read_root():
    return {"service": "importer", "status": "alive"}
