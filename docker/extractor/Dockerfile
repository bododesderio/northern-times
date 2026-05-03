FROM python:3.11.12-slim-bookworm

WORKDIR /app

RUN apt-get update && \
    apt-get install -y --no-install-recommends gcc libxml2-dev libxslt1-dev curl && \
    rm -rf /var/lib/apt/lists/*

# Playwright system dependencies (needed for Chromium)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
    libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
    libgbm1 libasound2 libpango-1.0-0 libcairo2 && \
    rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir torch==2.5.1+cpu --extra-index-url https://download.pytorch.org/whl/cpu && \
    pip install --no-cache-dir -r requirements.txt && \
    playwright install chromium

# Model cache directory (accessible by non-root user)
ENV TRANSFORMERS_CACHE=/app/.model_cache \
    HF_HOME=/app/.model_cache \
    SENTENCE_TRANSFORMERS_HOME=/app/.model_cache/sentence_transformers

RUN mkdir -p /app/.model_cache/sentence_transformers

# Pre-download models at build time so startup is fast
# DeBERTa for category classification + sentiment (~350MB)
RUN python -c "from transformers import pipeline; pipeline('zero-shot-classification', model='MoritzLaurer/deberta-v3-base-mnli-fever-anli')"
# DistilBART for summarization (~1.2GB)
RUN python -c "from transformers import pipeline; pipeline('summarization', model='sshleifer/distilbart-cnn-12-6')"
# all-MiniLM-L6-v2 for embeddings (~80MB)
RUN python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('sentence-transformers/all-MiniLM-L6-v2')"
# spaCy English model for NER (~12MB)
RUN python -m spacy download en_core_web_sm

COPY . .

# Run as non-root user
RUN useradd -m -r extractor && chown -R extractor:extractor /app
USER extractor

EXPOSE 5000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:5000/health')" || exit 1

# 1 worker: each worker loads all models (~2.8GB). 2 workers exceed the 4G container limit.
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000", "--workers", "1"]
