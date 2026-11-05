#!/bin/bash
# Fungsi bersama skrip deploy (di-source oleh deploy-prod.sh). Diuji oleh scripts/test-deploy-lib.sh.

# env_value BERKAS KUNCI: nilai KUNCI dari berkas .env tanpa tanda kutip pembungkus; kosong bila tidak ada.
env_value() {
    local line
    line=$(grep -E "^$2=" "$1" | tail -n1) || true
    line=${line#*=}
    line=${line%$'\r'}
    case "$line" in
        \"*\") line=${line#\"}; line=${line%\"} ;;
        \'*\') line=${line#\'}; line=${line%\'} ;;
    esac
    printf '%s' "$line"
}

# prune_backups FOLDER DATABASE HARI: hapus cadangan DATABASE-*.sql.gz yang lebih tua dari HARI hari.
prune_backups() {
    find "$1" -maxdepth 1 -type f -name "$2-*.sql.gz" -mtime "+$3" -delete
}

# backup_database KONTAINER DATABASE PENGGUNA SANDI FOLDER: tulis FOLDER/DATABASE-<stempel>.sql.gz dan cetak path-nya.
# Gagal (kode 1) tanpa meninggalkan berkas bila dump gagal, kosong, atau gzip-nya rusak.
# Kata sandi dioper lewat lingkungan (MYSQL_PWD), bukan argumen, supaya tidak tampil di daftar proses.
backup_database() {
    local container=$1 db=$2 user=$3 password=$4 dir=$5
    local file="$dir/$db-$(date +%Y%m%d-%H%M%S).sql.gz"
    local partial="$file.partial"

    mkdir -p "$dir"
    chmod 700 "$dir"

    if ! (
        set -o pipefail
        MYSQL_PWD="$password" docker exec -e MYSQL_PWD "$container" \
            mysqldump --single-transaction --routines --no-tablespaces -u"$user" "$db" | gzip > "$partial"
    ) || ! gzip -t "$partial" 2>/dev/null || [ "$(stat -c %s "$partial")" -lt 200 ]; then
        rm -f "$partial"
        return 1
    fi

    chmod 600 "$partial"
    mv "$partial" "$file"
    printf '%s' "$file"
}
