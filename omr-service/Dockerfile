FROM python:3.11-slim
WORKDIR /app
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    OMR_STORAGE_DIR=/tmp/omr
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
        libsm6 \
        libxrender1 \
        libxext6 \
        libx11-6 \
        libxcb1 \
    && rm -rf /var/lib/apt/lists/*
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt
COPY . .

# ensure the storage dir exists and is writable (default from OMR_STORAGE_DIR)
RUN mkdir -p ${OMR_STORAGE_DIR} \
    && chmod 777 ${OMR_STORAGE_DIR}

CMD ["sh", "-c", "uvicorn main:app --host 0.0.0.0 --port ${PORT:-8000}"]
