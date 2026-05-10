FROM python:3.11-slim
WORKDIR /app

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    OMR_STORAGE_DIR=/tmp/omr

# Copy requirements first to leverage Docker layer caching
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# ensure the storage dir exists and is writable
RUN mkdir -p ${OMR_STORAGE_DIR} \
    && chmod 777 ${OMR_STORAGE_DIR}

CMD ["sh", "-c", "uvicorn main:app --host 0.0.0.0 --port ${PORT:-8000}"]