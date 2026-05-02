#!/bin/sh
# Ollama entrypoint — starts server and pulls model on first run

MODEL="${OLLAMA_MODEL:-llama3:8b}"

# Start Ollama server in background
ollama serve &
OLLAMA_PID=$!

# Wait for server to be ready
echo "Waiting for Ollama to be ready..."
until curl -sf http://localhost:11434/api/health > /dev/null 2>&1; do
  sleep 1
done
echo "Ollama ready."

# Pull model if not already cached
if ! ollama list | grep -q "^${MODEL}"; then
  echo "Pulling model: ${MODEL}"
  ollama pull "${MODEL}"
  echo "Model ${MODEL} ready."
else
  echo "Model ${MODEL} already cached."
fi

# Keep server running
wait $OLLAMA_PID
