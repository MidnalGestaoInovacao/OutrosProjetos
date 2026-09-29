#!/usr/bin/env bash
# Compila o CSS do site: Tailwind (só as classes usadas) + CSS original do site + camada WordPress.
# Requer Node.js. Uso: bash site/build_css.sh   (gera site/build/bs.min.css)
set -euo pipefail
cd "$(dirname "$0")"
TW_DIR="${TW_DIR:-$(pwd)/.tw}"
if [ ! -x "$TW_DIR/node_modules/.bin/tailwindcss" ]; then
  mkdir -p "$TW_DIR" && (cd "$TW_DIR" && npm init -y >/dev/null && npm i -s tailwindcss@3.4.17 clean-css-cli@5 >/dev/null)
fi
mkdir -p build
printf '@tailwind base;\n@tailwind components;\n@tailwind utilities;\n' > build/tw-input.css
"$TW_DIR/node_modules/.bin/tailwindcss" -c tailwind.config.js -i build/tw-input.css -o build/tw.css 2>/dev/null
# Mesma ordem do site original: CSS do site antes do Tailwind (as utilidades responsivas vencem).
cat css/original.css build/tw.css css/wp.css > build/bs.css
"$TW_DIR/node_modules/.bin/cleancss" -O1 -o build/bs.min.css build/bs.css
rm -f build/tw-input.css build/tw.css
wc -c build/bs.css build/bs.min.css
