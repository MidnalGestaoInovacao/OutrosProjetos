#!/bin/bash
# Segunda rodada: sobe as imagens restantes, reconstrói com o mapa final de mídia, republica blocos e páginas, limpa páginas de teste.
set -u
cd "$(dirname "$0")/.."
SCRATCH=/tmp/claude-0/-home-user-OutrosProjetos/c6c5b86e-91b8-57fd-9cce-9e053bdb5efc/scratchpad/trix
export WPMCP_KEY="${WPMCP_KEY:?defina WPMCP_KEY}"
echo "== uploads $(date +%T)"; python3 src/compute_media_todo.py "$SCRATCH"; (cd "$SCRATCH" && python3 upload_media.py)
cp "$SCRATCH/media_map.json" media_map.json
echo "== build $(date +%T)"; python3 src/build.py --media media_map.json
echo "== blocks $(date +%T)"; python3 src/deploy.py --only blocks
echo "== pages $(date +%T)"; python3 src/deploy.py --only pages
echo "== cleanup $(date +%T)"; python3 src/deploy.py --only cleanup
echo "== done $(date +%T)"
