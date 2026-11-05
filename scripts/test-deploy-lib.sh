#!/bin/bash
# Uji scripts/lib/deploy.sh tanpa Docker sungguhan: `docker` ditiru dengan fungsi. Jalankan: bash scripts/test-deploy-lib.sh
set -u
cd "$(dirname "$0")/.."
source scripts/lib/deploy.sh

fail=0
check() { # check "nama" "diharapkan" "aktual"
    if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  GAGAL $1: diharapkan [$2], didapat [$3]"; fail=1; fi
}
tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT

echo "env_value"
cat > "$tmp/.env" <<'ENV'
DB_DATABASE=sijebol_prod
DB_USERNAME="sijebol"
DB_PASSWORD='Sandi=dengan=sama#dengan spasi'
DB_HOST=sijebol-mysql
EMPTY=
WIN=nilai-crlf
ENV
printf 'CRLF=nilai\r\n' >> "$tmp/.env"
check "nilai biasa" "sijebol_prod" "$(env_value "$tmp/.env" DB_DATABASE)"
check "tanda kutip ganda dibuang" "sijebol" "$(env_value "$tmp/.env" DB_USERNAME)"
check "tanda kutip tunggal, tanda sama dengan, dan spasi dipertahankan" "Sandi=dengan=sama#dengan spasi" "$(env_value "$tmp/.env" DB_PASSWORD)"
check "kunci kosong" "" "$(env_value "$tmp/.env" EMPTY)"
check "kunci tidak ada" "" "$(env_value "$tmp/.env" TIDAK_ADA)"
check "akhir baris CRLF dibuang" "nilai" "$(env_value "$tmp/.env" CRLF)"
check "tidak cocok sebagian nama kunci" "" "$(env_value "$tmp/.env" DB_DATA)"

echo "backup_database"
docker() { # tiruan: `docker exec -e MYSQL_PWD <kontainer> mysqldump ...`
    [ "${MYSQL_PWD:-}" = "rahasia" ] || { echo "kata sandi tidak diteruskan" >&2; return 2; }
    echo "-- MySQL dump palsu"
    seq 1 500 | sed 's/^/-- baris /'
}
dir="$tmp/backups"
file=$(backup_database sijebol-mysql sijebol_prod sijebol rahasia "$dir"); status=$?
check "berhasil" "0" "$status"
check "nama berkas memuat nama database dan ekstensi" "yes" "$([[ $file == "$dir"/sijebol_prod-*.sql.gz ]] && echo yes || echo no)"
check "isi dapat dibaca dan berasal dari dump" "yes" "$(gunzip -c "$file" | grep -q 'MySQL dump palsu' && echo yes || echo no)"
# Izin POSIX tidak dapat diuji di sistem berkas tanpa dukungan chmod (mis. NTFS lewat Git Bash).
touch "$tmp/probe"
chmod 600 "$tmp/probe"
if [ "$(stat -c %a "$tmp/probe")" = "600" ]; then
    check "izin berkas 600" "600" "$(stat -c %a "$file")"
    check "izin folder 700" "700" "$(stat -c %a "$dir")"
else
    echo "  lewati izin berkas/folder (sistem berkas tanpa chmod)"
fi
check "tidak ada sisa .partial" "0" "$(find "$dir" -name '*.partial' | wc -l | tr -d ' ')"

docker() { echo "mysqldump gagal" >&2; return 1; }
rm -f "$dir"/*
backup_database sijebol-mysql sijebol_prod sijebol rahasia "$dir" >/dev/null 2>&1; status=$?
check "dump gagal = fungsi gagal" "1" "$status"
check "dump gagal tidak meninggalkan berkas" "0" "$(find "$dir" -type f | wc -l | tr -d ' ')"

docker() { return 0; } # keluaran kosong, kode sukses: tidak boleh dianggap cadangan sah
backup_database sijebol-mysql sijebol_prod sijebol rahasia "$dir" >/dev/null 2>&1; status=$?
check "dump kosong ditolak" "1" "$status"
check "dump kosong tidak meninggalkan berkas" "0" "$(find "$dir" -type f | wc -l | tr -d ' ')"

echo "prune_backups"
mkdir -p "$dir"
rm -f "$dir"/*
touch "$dir/sijebol_prod-baru.sql.gz"
touch -d '20 days ago' "$dir/sijebol_prod-lama.sql.gz"
touch -d '13 days ago' "$dir/sijebol_prod-hampir.sql.gz"
touch -d '30 days ago' "$dir/sijebol_uat-lain.sql.gz"
touch -d '30 days ago' "$dir/catatan.txt"
prune_backups "$dir" sijebol_prod 14
check "yang lebih tua dari 14 hari dihapus" "no" "$([ -e "$dir/sijebol_prod-lama.sql.gz" ] && echo yes || echo no)"
check "13 hari dipertahankan" "yes" "$([ -e "$dir/sijebol_prod-hampir.sql.gz" ] && echo yes || echo no)"
check "yang baru dipertahankan" "yes" "$([ -e "$dir/sijebol_prod-baru.sql.gz" ] && echo yes || echo no)"
check "cadangan database lain tidak disentuh" "yes" "$([ -e "$dir/sijebol_uat-lain.sql.gz" ] && echo yes || echo no)"
check "berkas selain cadangan tidak disentuh" "yes" "$([ -e "$dir/catatan.txt" ] && echo yes || echo no)"

[ $fail -eq 0 ] && echo "SEMUA LULUS" || { echo "ADA YANG GAGAL"; exit 1; }
