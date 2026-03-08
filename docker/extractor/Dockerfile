FROM python:3.11.12-slim-bookworm

WORKDIR /app

RUN apt-get update && \
    apt-get install -y --no-install-recommends gcc libxml2-dev libxslt1-dev curl && \
    rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir torch==2.5.1+cpu --extra-index-url https://download.pytorch.org/whl/cpu && \
    pip install --no-cache-dir -r requirements.txt

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
    CMD curl -f http://localhost:5000/health || exit 1

# Reduced from 4 to 2 workers to fit memory budget (~2.8GB models + overhead)
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000", "--workers", "2"]
