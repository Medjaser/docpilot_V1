#!/usr/bin/env bash
# Installation initiale de l'environnement local DocPilot (à lancer une seule fois).
# Prérequis : Docker Desktop (ou Docker Engine + Compose v2).
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> 1/7 Fichier .env"
[ -f .env ] || cp .env.example .env

echo "==> 2/7 Construction et démarrage des conteneurs"
docker compose up -d --build

echo "==> 3/7 Dépendances PHP (Laravel + Livewire + stockage S3)"
docker compose exec app composer install
docker compose exec app composer require livewire/livewire league/flysystem-aws-s3-v3

echo "==> 4/7 Clé d'application"
docker compose exec app php artisan key:generate

echo "==> 5/7 Attente de PostgreSQL"
until docker compose exec pgsql pg_isready -U docpilot >/dev/null 2>&1; do sleep 2; done

echo "==> 6/7 Base de données + données de démonstration (BatiNova)"
docker compose exec app php artisan migrate:fresh --seed

echo "==> 7/7 Front-end (Tailwind / Vite)"
docker compose exec app npm install
docker compose exec app npm run build

docker compose restart queue scheduler

cat <<'TXT'

DocPilot est prêt.
  Application  : http://localhost:8000
  E-mails      : http://localhost:8025   (Mailpit)
  Stockage S3  : http://localhost:9001   (MinIO — docpilot / docpilot-secret)

Lancer les tests : docker compose exec app php artisan test
TXT
