FROM python:3.11-slim

WORKDIR /app

RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /data/knowledge

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy all application files
COPY app.py .
COPY config.py .
COPY models.py .
COPY constants.py .
COPY utils.py .
COPY lifecycle.py .
COPY services/ ./services/
COPY index.html .
COPY knowledge/ /data/knowledge/

CMD ["python", "app.py"]
