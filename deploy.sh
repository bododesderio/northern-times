#!/usr/bin/env bash
#
# Deploy Northern Times — build, (re)start the stack, then reload nginx.
#
# WHY the nginx restart: docker/nginx/*.conf is a SINGLE-FILE bind mount, which
# Docker pins by inode. Editing the file (any tool that writes-then-renames)
# creates a NEW inode, but `docker compose up -d` will NOT recreate the nginx
# container — its compose config is unchanged — so it keeps serving the OLD
# config. Restarting the `web` service re-resolves the mount and loads the new
# config. Both the dev and prod compose files name the nginx service `web`, so
# restarting by service works regardless of container_name.
#
# Usage:
#   ./deploy.sh                     # dev stack (docker-compose.django.yml)
#   ./deploy.sh --prod              # prod stack (docker-compose.django.prod.yml)
#   ./deploy.sh --mailpit           # dev stack + Mailpit dev inbox overlay
#   ./deploy.sh -- --no-cache       # pass extra args through to `up`/`build`
#
set -euo pipefail
cd "$(dirname "$0")"

FILES=(-f docker-compose.django.yml)

while [[ $# -gt 0 ]]; do
  case "$1" in
    --prod)    FILES=(-f docker-compose.django.prod.yml); shift ;;
    --mailpit) FILES+=(-f docker-compose.mailpit.yml); shift ;;
    --)        shift; break ;;   # everything after -- goes to `up`
    *)         break ;;
  esac
done

echo "▶ Building and starting: docker compose ${FILES[*]} up -d --build $*"
docker compose "${FILES[@]}" up -d --build "$@"

# Build is fully done here (`up` blocks until images are built and containers
# are started). Now restart nginx so config edits take effect.
echo "▶ Restarting nginx (web) to pick up docker/nginx config changes…"
docker compose "${FILES[@]}" restart web

echo "✓ Deploy complete — stack is up and nginx reloaded."
