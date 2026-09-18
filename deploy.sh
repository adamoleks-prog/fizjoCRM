#!/usr/bin/env bash
# Deploy do produkcji: commit lokalny (opcjonalnie) -> push -> wdrozenie przez SSH.
#
# Uzycie:
#   ./deploy.sh "opis commita"   -> git add -A, commit, push, deploy
#   ./deploy.sh                  -> tylko push (jesli juz jest commit) i deploy
#
# Serwer buduje assety sam (Node jest zainstalowany w ~/bin) - z tego komputera
# nic nie jest przesylane poza kodem przez git.
set -euo pipefail

SSH_KEY="$HOME/.ssh/id_ed25519_fizjoroom"
SERVER="panel@51.222.86.126"
REMOTE_APP_DIR="fizjoCRM"

if [ -n "${1:-}" ]; then
    echo "==> Commit lokalny"
    git add -A
    git commit -m "$1" || echo "(nic do scommitowania, kontynuuję)"
fi

echo "==> Push do GitHub"
git push origin master

echo "==> Wdrożenie na panel.fizjoroom.pl"
ssh -i "$SSH_KEY" "$SERVER" bash -s -- "$REMOTE_APP_DIR" <<'REMOTE_SCRIPT'
set -euo pipefail
export PATH="$HOME/bin:$PATH"
cd "$HOME/$1"

echo "  -- tryb konserwacji"
php artisan down || true

echo "  -- git pull"
git pull origin master

echo "  -- composer install"
composer install --no-dev --optimize-autoloader --no-interaction

echo "  -- npm ci && npm run build"
npm ci
npm run build

echo "  -- migracje bazy danych"
php artisan migrate --force

echo "  -- cache konfiguracji/tras/widoków"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "  -- restart kolejki (na wypadek działającego workera)"
php artisan queue:restart

echo "  -- wyłączenie trybu konserwacji"
php artisan up

echo "  -- gotowe, aktualny commit:"
git log --oneline -1
REMOTE_SCRIPT

echo "==> Wdrożenie zakończone."
