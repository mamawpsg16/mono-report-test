# Python Service Setup (FastAPI)

How to create the `python-service/` from an empty folder and get a FastAPI app
running locally. Each step lists the command, what it does, and what to expect.

The Python service is the part that does the heavy import work (reading
CSV/Excel, validating rows, bulk-inserting into PostgreSQL). FastAPI gives it an
HTTP "door" so Laravel can trigger it.

---

## Prerequisites

- Python 3.10+ installed (`python3 --version`)
- `pip` and the `venv` module available

---

## 1. Create the folder and enter it

```bash
cd /home/kevin/Desktop/personal-dev/mono-report-test
mkdir python-service
cd python-service
```

**What this does:** makes an empty project folder and moves into it. Everything
below happens inside `python-service/`.

**Expect:** `pwd` prints `.../mono-report-test/python-service`.

---

## 2. Create a virtual environment

```bash
python3 -m venv .venv
```

**What this does:** creates a `.venv/` folder containing a _private_ copy of
Python and a _private_ space for installed packages.

**Why:** packages you install go into `.venv/` instead of system-wide Python.
Each project keeps its own dependencies, so versions never clash between
projects. `.venv/` is disposable — you can delete and recreate it anytime.

**Expect:** a new `.venv/` folder. Check with `ls -a`.

---

## 3. Activate the virtual environment

```bash
source .venv/bin/activate
```

**What this does:** points `python` and `pip` at the copies inside `.venv/`.

**Expect:** your shell prompt now starts with `(.venv)`. That means you are
"inside the box". Run `deactivate` to leave it.

> Note: you must activate the venv in **every new terminal** before running the
> app. If you forget, `uvicorn`/`pip` may not be found, or use the wrong Python.

---

## 4. Install FastAPI and the web server

```bash
pip install fastapi "uvicorn[standard]"
pip install "psycopg[binary]"
```

**What this does:** downloads packages from PyPI (the Python package index) into
your venv.

- **fastapi** — the web framework (defines endpoints, validation, docs).
- **uvicorn** — the actual web server that listens on a port and runs the app.
  FastAPI by itself cannot listen on a port; uvicorn does that. `[standard]`
  pulls in extra packages that make it faster.
- **pydantic** and **starlette** install automatically — FastAPI is built on
  top of them (Pydantic = data validation, Starlette = web internals).

**Expect:** output ending in `Successfully installed fastapi-... uvicorn-...`.
Check with `pip list`.

---

## 5. Lock the dependencies

```bash
pip freeze > requirements.txt
```

**What this does:** `pip freeze` prints every installed package with its _exact_
version (e.g. `fastapi==0.115.14`). The `>` redirects that output into a file
called `requirements.txt`.

**Why:** `requirements.txt` is the recipe for this environment. On another
machine (or in Docker), running `pip install -r requirements.txt` rebuilds the
_exact same_ set of packages and versions. Without it, someone else might get
newer, incompatible versions.

**Expect:** a `requirements.txt` file. Check with `cat requirements.txt`.

---

## 6. Create the app file

Create a file named `main.py` inside `python-service/` with this content:

```python
from fastapi import FastAPI

app = FastAPI(title="Importer Service")

@app.get("/")
def read_root():
    return {"service": "importer", "status": "alive"}

@app.get("/imports/{import_id}")
def get_import(import_id: int):
    return {"import_id": import_id, "status": "not_implemented_yet"}
```

**What this does, line by line:**

- `from fastapi import FastAPI` — bring in the framework.
- `app = FastAPI(...)` — create the application object. uvicorn looks for this
  variable.
- `@app.get("/")` — a _decorator_ that registers the function below as the
  handler for `GET /`. The function returning a `dict` is auto-converted to
  JSON.
- `@app.get("/imports/{import_id}")` — `{import_id}` is a path parameter. The
  type hint `import_id: int` makes FastAPI validate and convert it: `/imports/5`
  becomes the integer `5`, while `/imports/abc` returns an automatic `422`
  error before your code runs.

---

## 7. Run the server

```bash
uvicorn main:app --reload --port 8001
```

**What this does:** starts the web server.

- `main:app` — load the `app` object from `main.py` (`main` = the file,
  `app` = the variable).
- `--reload` — automatically restart when you save a code change (development
  only).
- `--port 8001` — listen on port 8001.

**Expect:** `Uvicorn running on http://127.0.0.1:8001`. Leave this terminal
open — the server runs here. Press `Ctrl+C` to stop it.

---

## 8. Check it works

With the server running, open in a browser:

- <http://localhost:8001/> — the root endpoint (JSON response).
- <http://localhost:8001/docs> — interactive API docs (Swagger UI), generated
  automatically from your code. Click an endpoint, then "Try it out".

If `/docs` does not load, the server is almost certainly not running — start it
again in step 7 and keep that terminal open.

---

## Quick reference

| Command                                 | Purpose                         |
| --------------------------------------- | ------------------------------- |
| `python3 -m venv .venv`                 | create the isolated environment |
| `source .venv/bin/activate`             | enter the environment           |
| `deactivate`                            | leave the environment           |
| `pip install <pkg>`                     | install a package into the venv |
| `pip freeze > requirements.txt`         | save exact versions to a file   |
| `pip install -r requirements.txt`       | rebuild env from the file       |
| `uvicorn main:app --reload --port 8001` | run the app                     |

---

## Daily workflow (after first setup)

You only do steps 1–6 once. Day to day:

```bash
cd python-service
source .venv/bin/activate
uvicorn main:app --reload --port 8001
```
