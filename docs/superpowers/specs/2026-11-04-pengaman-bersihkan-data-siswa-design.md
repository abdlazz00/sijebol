# Pengaman `sijebol:bersihkan-data-siswa` di luar lokal (F-11)

Sumber: temuan F-11 di `docs/reviews/2026-11-02-full-code-review.md`.

## Masalah

`sijebol:bersihkan-data-siswa` menghapus seluruh siswa, pendaftaran, sesi/jadwal, dokumen, dan berkas dokumen. Dengan `--force` tidak ada konfirmasi, dan perintah ikut terpasang di image produksi. Satu ketikan `--force` yang salah target menghapus data produksi tanpa jalan kembali.

## Keputusan (pemilik, 4 Nov 2026)

**Ketik nama lingkungan.** Ditolak total di produksi tidak dipilih karena alur rilis yang terdokumentasi memakai perintah ini (membersihkan data uji sebelum migrasi pertama).

## Rancangan

- Lokal dan testing: perilaku lama (konfirmasi, `--force` melewatinya).
- Lingkungan lain (UAT, produksi, dan sebagainya):
  - `--force` diabaikan.
  - Perintah menampilkan `Lingkungan <env>, database <nama>.` dan `<tabel>: <n> baris akan dihapus` untuk tiap tabel.
  - Operator harus mengetik nama lingkungan persis sama dengan `APP_ENV` (huruf besar/kecil dibedakan), lewat prompt `Ketik nama lingkungan (<env>) untuk melanjutkan`, atau lewat opsi `--lingkungan=<env>` untuk skrip tanpa terminal interaktif.
  - Salah, kosong, atau tanpa terminal interaktif dan tanpa opsi = dibatalkan dengan kode keluar 1, tanpa menghapus apa pun.
- Opsi `--lingkungan` hanya dipakai di luar lokal.

## Di luar cakupan
- Cadangan otomatis sebelum menghapus: perintah artisan tidak membuat `mysqldump`; cadangan ada di `scripts/deploy-prod.sh`. Panduan tetap meminta cadangan manual sebelum membersihkan data.

## Pengujian
Pest `ResetStudentDataTest`: di lingkungan `production` `--force` saja ditolak, nama salah (termasuk beda huruf besar/kecil) ditolak, mengetik nama yang benar menghapus dan menampilkan database serta jumlah baris lebih dulu, `--lingkungan` yang cocok bekerja tanpa terminal interaktif. Empat tes lama (lokal) lulus tanpa diubah.
