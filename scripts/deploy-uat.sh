#!/bin/bash
set -e

echo "🚀 [SIJEBOL] Memulai deployment UAT (uat-seibeduk.sijebol.online)..."

PROJECT_DIR="/opt/sijebol/uat/app"

if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Direktori $PROJECT_DIR tidak ditemukan."
    exit 1
fi

cd "$PROJECT_DIR"

echo "📥 Menarik pembaruan dari branch staging..."
git pull origin staging

echo "🐳 Membangun dan menjalankan ulang container UAT..."
docker compose -f docker-compose.uat.yml up -d --build

echo "🔄 Me-restart worker antrean (queue)..."
docker exec sijebol-uat php artisan queue:restart

echo "⚡ Mengoptimalkan cache Laravel..."
docker exec sijebol-uat php artisan config:cache || true
docker exec sijebol-uat php artisan route:cache || true
docker exec sijebol-uat php artisan view:cache || true

echo "✅ [SIJEBOL] Deployment UAT Berhasil dan Siap Ditest!"
