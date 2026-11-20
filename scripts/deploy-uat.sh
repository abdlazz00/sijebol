#!/bin/bash
# Deploy UAT: git pull staging -> build (--pull) -> up -d -> queue:restart -> cache.
# Pemakaian: scripts/deploy-uat.sh [--no-cache]
set -euo pipefail

BUILD_FLAGS=(--pull)
for arg in "$@"; do
    case "$arg" in
        --no-cache) BUILD_FLAGS+=(--no-cache) ;;
        *)
            echo "❌ Opsi tidak dikenal: $arg"
            echo "   Pemakaian: $0 [--no-cache]"
            exit 1
            ;;
    esac
done

echo "🚀 [SIJEBOL] Memulai deployment UAT (uat-seibeduk.sijebol.online)..."

PROJECT_DIR="${PROJECT_DIR:-/opt/sijebol/uat/app}"

if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Direktori $PROJECT_DIR tidak ditemukan."
    exit 1
fi

cd "$PROJECT_DIR"

echo "📥 Menarik pembaruan dari branch staging..."
git pull origin staging

echo "🐳 Membangun image UAT..."
docker compose -p sijebol-uat -f docker-compose.uat.yml build "${BUILD_FLAGS[@]}"

echo "🔄 Menjalankan ulang container UAT..."
docker compose -p sijebol-uat -f docker-compose.uat.yml up -d

echo "⏳ Menunggu aplikasi UAT siap..."
ready=false
for _ in $(seq 1 30); do
    if docker exec sijebol-uat php artisan --version >/dev/null 2>&1; then ready=true; break; fi
    sleep 2
done
if [ "$ready" != "true" ]; then
    echo "❌ Kontainer UAT tidak merespons dalam 60 detik."
    exit 1
fi

echo "🔄 Me-restart worker antrean (queue)..."
docker exec sijebol-uat php artisan queue:restart

echo "⚡ Mengoptimalkan cache Laravel..."
docker exec sijebol-uat php artisan config:cache || true
docker exec sijebol-uat php artisan route:cache || true
docker exec sijebol-uat php artisan view:cache || true

echo "✅ [SIJEBOL] Deployment UAT Berhasil dan Siap Ditest!"
