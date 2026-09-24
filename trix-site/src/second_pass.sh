#!/bin/bash
# Segunda rodada: sobe as imagens restantes, reconstrói com o mapa final de mídia, republica blocos e páginas, limpa páginas de teste.
set -u
cd "$(dirname "$0")/.."
SCRATCH=/tmp/claude-0/-home-user-OutrosProjetos/c6c5b86e-91b8-57fd-9cce-9e053bdb5efc/scratchpad/trix
export WPMCP_KEY="${WPMCP_KEY:?defina WPMCP_KEY}"
PY=${PYTHON:-python3}
echo "== uploads $(date +%T)"; $PY src/compute_media_todo.py "$SCRATCH"; $PY src/media/upload_media.py "$SCRATCH/images" media_map.json "$SCRATCH/upload_todo.json"
echo "== build $(date +%T)"; $PY src/build.py --media media_map.json
echo "== blocks $(date +%T)"; $PY src/deploy.py --only blocks
echo "== pages $(date +%T)"; $PY src/deploy.py --only pages
echo "== cleanup $(date +%T)"; $PY src/deploy.py --only cleanup
echo "== done $(date +%T)"
