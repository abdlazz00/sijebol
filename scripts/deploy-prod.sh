#!/bin/bash
set -e

echo "🚀 [SIJEBOL] Memulai deployment Production (sungaibeduk.sijebol.online)..."

PROJECT_DIR="/opt/sijebol/prod/app"

if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Direktori $PROJECT_DIR tidak ditemukan."
    exit 1
fi

cd "$PROJECT_DIR"

echo "📥 Menarik pembaruan dari branch main..."
git pull origin main

echo "🐳 Membangun dan menjalankan ulang container production..."
docker compose -f docker-compose.prod.yml up -d --build

echo "🔄 Me-restart worker antrean (queue)..."
docker exec sijebol-prod php artisan queue:restart

echo "⚡ Mengoptimalkan cache Laravel..."
docker exec sijebol-prod php artisan config:cache || true
docker exec sijebol-prod php artisan route:cache || true
docker exec sijebol-prod php artisan view:cache || true

echo "✅ [SIJEBOL] Deployment Production Berhasil dan Live!"
