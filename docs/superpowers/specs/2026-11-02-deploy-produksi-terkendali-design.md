# Deploy produksi terkendali — desain

Asal: temuan F-02 di `docs/reviews/2026-11-02-full-code-review.md`.

## Masalah
`AUTO_MIGRATE=true` menjalankan `migrate --force` pada setiap start kontainer (deploy, reboot, restart, OOM). Migrasi yang gagal menyebabkan crash-loop (`set -e` + `restart: unless-stopped`). Tidak ada cadangan database dan tidak ada titik berhenti sebelum skema berubah. Migrasi konversi pendaftaran tidak punya `down()`.

## Keputusan (pengguna)
- Produksi manual + cadangan otomatis di skrip; UAT tetap otomatis.
- Database produksi baru/kosong (migrasi konversi lolos di rilis pertama).
- Cadangan di folder VPS, retensi 14 hari.

## Keputusan builder
- Alur: build -> daftar migrasi tertunda + konfirmasi -> `artisan down` -> cadangan -> ganti kontainer -> migrasi -> `up`. Build dilakukan lebih dulu agar henti layanan hanya sepanjang penggantian kontainer.
- Mode pemeliharaan memakai berkas di `storage` (volume), sehingga kontainer baru ikut dalam mode itu tanpa jendela terbuka.
- Gagal pada migrasi = tetap dalam mode pemeliharaan (jangan melayani skema setengah jadi). Gagal pada cadangan = buka kembali aplikasi, tidak ada yang berubah.
- Kata sandi DB dioper lewat lingkungan (`MYSQL_PWD`), bukan argumen.
- Cadangan dianggap sah hanya bila dump sukses, gzip utuh, dan ukuran memadai; ditulis sebagai `.partial` lalu di-rename.
- Fungsi bantu dipisah ke `scripts/lib/deploy.sh` agar bisa diuji tanpa Docker.

## Bukan tujuan
Salinan cadangan di luar VPS, pemantauan, rollback otomatis, dan perubahan skrip UAT.
