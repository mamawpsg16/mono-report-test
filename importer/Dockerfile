# --- Python importer worker ---
FROM python:3.11-slim

# Create a non-root user (security: never run app code as root)
RUN useradd --create-home worker
WORKDIR /home/worker/app

# Install Python dependencies first (cache layer)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy the app code
COPY . .

# Switch to non-root user
USER worker

CMD ["python", "-u", "worker.py"]
