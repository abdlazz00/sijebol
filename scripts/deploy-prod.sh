#!/bin/bash
# Deploy produksi: build -> cek migrasi tertunda -> mode pemeliharaan -> cadangan DB -> ganti kontainer -> migrasi -> buka.
# Migrasi TIDAK otomatis saat kontainer mulai (AUTO_MIGRATE=false); hanya dijalankan di langkah ini, setelah cadangan.
# Pemakaian: scripts/deploy-prod.sh [--yes]   (--yes melewati konfirmasi, untuk otomasi)
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/opt/sijebol/prod/app}"
BACKUP_DIR="${BACKUP_DIR:-/opt/sijebol/backups}"
MYSQL_CONTAINER="sijebol-mysql"
APP_CONTAINER="sijebol-prod"
RETENTION_DAYS=14

if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ Direktori $PROJECT_DIR tidak ditemukan."
    exit 1
fi
cd "$PROJECT_DIR"

echo "🚀 [SIJEBOL] Memulai deployment Production (sungaibeduk.sijebol.online)..."
echo "📥 Menarik pembaruan dari branch main..."
git pull origin main

# Dimuat setelah git pull agar selalu versi terbaru.
source scripts/lib/deploy.sh

compose() { docker compose -p sijebol-prod -f docker-compose.prod.yml "$@"; }

DB_NAME=$(env_value .env DB_DATABASE)
DB_USER=$(env_value .env DB_USERNAME)
DB_PASS=$(env_value .env DB_PASSWORD)
if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    echo "❌ DB_DATABASE, DB_USERNAME, atau DB_PASSWORD kosong di .env; cadangan tidak mungkin dibuat."
    exit 1
fi

BACKUP_FILE=""
on_error() {
    echo ""
    echo "❌ Deploy gagal. Jika aplikasi sudah masuk mode pemeliharaan, ia TETAP dalam mode itu supaya tidak melayani skema setengah jadi."
    if [ -n "$BACKUP_FILE" ]; then
        echo "   Cadangan sebelum deploy: $BACKUP_FILE"
        echo "   Pulihkan: gunzip -c $BACKUP_FILE | docker exec -i -e MYSQL_PWD $MYSQL_CONTAINER mysql -u$DB_USER $DB_NAME   (set MYSQL_PWD lebih dulu)"
    fi
    echo "   Setelah masalah diperbaiki: docker exec $APP_CONTAINER php artisan up"
}
trap on_error ERR

echo "🐳 Membangun image baru (aplikasi lama tetap melayani)..."
compose build

echo "🔎 Migrasi yang akan dijalankan:"
compose run --rm -T --no-deps --entrypoint php app artisan migrate:status --pending \
    || echo "   (status migrasi tidak terbaca; wajar pada instalasi pertama karena tabel migrations belum ada)"

if [ "${1:-}" != "--yes" ]; then
    read -r -p "Lanjutkan deploy? Aplikasi akan masuk mode pemeliharaan sebentar. [y/N] " answer
    if [[ ! "$answer" =~ ^[yY]$ ]]; then
        echo "Dibatalkan. Tidak ada yang berubah (image baru sudah dibangun, kontainer lama masih berjalan)."
        trap - ERR
        exit 1
    fi
fi

echo "🚧 Mode pemeliharaan..."
docker exec "$APP_CONTAINER" php artisan down --retry=60 || echo "   (kontainer lama belum berjalan; dilewati)"

echo "💾 Mencadangkan database $DB_NAME..."
if ! BACKUP_FILE=$(backup_database "$MYSQL_CONTAINER" "$DB_NAME" "$DB_USER" "$DB_PASS" "$BACKUP_DIR"); then
    BACKUP_FILE=""
    echo "❌ Cadangan gagal; deploy dihentikan dan aplikasi dibuka kembali."
    docker exec "$APP_CONTAINER" php artisan up || true
    trap - ERR
    exit 1
fi
echo "   Tersimpan: $BACKUP_FILE"
prune_backups "$BACKUP_DIR" "$DB_NAME" "$RETENTION_DAYS"

echo "🔄 Mengganti kontainer..."
compose up -d

echo "⏳ Menunggu aplikasi siap..."
ready=false
for _ in $(seq 1 30); do
    if docker exec "$APP_CONTAINER" php artisan --version >/dev/null 2>&1; then ready=true; break; fi
    sleep 2
done
if [ "$ready" != "true" ]; then
    echo "❌ Kontainer baru tidak merespons dalam 60 detik."
    false
fi

echo "🗄️  Menjalankan migrasi..."
docker exec "$APP_CONTAINER" php artisan migrate --force

echo "🔄 Me-restart worker antrean (queue)..."
docker exec "$APP_CONTAINER" php artisan queue:restart

echo "⚡ Mengoptimalkan cache Laravel..."
docker exec "$APP_CONTAINER" php artisan config:cache || true
docker exec "$APP_CONTAINER" php artisan route:cache || true
docker exec "$APP_CONTAINER" php artisan view:cache || true

docker exec "$APP_CONTAINER" php artisan up
trap - ERR
echo "✅ [SIJEBOL] Deployment Production Berhasil dan Live! (cadangan: $BACKUP_FILE)"
