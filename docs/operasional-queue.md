# Operasional: queue worker dan scheduler

Email dan notifikasi dikirim lewat antrean (`QUEUE_CONNECTION=database`), dan pembersihan log
berjalan lewat scheduler. Keduanya butuh proses yang selalu hidup di server.

## Lokal

```bash
php artisan queue:work          # memproses email/notifikasi
php artisan schedule:work       # menjalankan jadwal (mis. logs:prune harian)
```

Dengan `MAIL_MAILER=log`, email tidak dikirim keluar melainkan ditulis ke `storage/logs/laravel.log`.

## Produksi (supervisor)

```ini
[program:sijebol-worker]
command=php /path/ke/sijebol/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/ke/sijebol/storage/logs/worker.log
stopwaitsecs=3600
```

Cron untuk scheduler (satu baris):

```cron
* * * * * cd /path/ke/sijebol && php artisan schedule:run >> /dev/null 2>&1
```

Setelah deploy, jalankan `php artisan queue:restart` agar worker memuat kode baru. Job yang gagal
permanen (setelah 3 percobaan) masuk tabel `failed_jobs`; periksa dengan `php artisan queue:failed`
dan ulangi dengan `php artisan queue:retry all`.

## Impor siswa

Impor Excel (Siswa, Impor) diproses di queue oleh `ImportStudentsJob`, sehingga **queue worker wajib berjalan**
(lokal: `php artisan queue:work`; produksi: program supervisor di atas). Tanpa worker impor tetap berstatus `antri`
dan dialog menampilkan petunjuk setelah 20 detik. Berkas unggahan (berisi NIK) disimpan sementara di
`storage/app/private/imports` dan dihapus setelah diproses, baik berhasil maupun gagal. Hasil (jumlah berhasil dan
baris gagal) tersimpan di tabel `student_imports`. Job impor tidak diulang otomatis (`tries = 1`); bila gagal,
impor ditandai `gagal` dan pembuatnya menerima notifikasi.

## SMTP

Penyedia SMTP belum ditentukan (spec v1 bagian 11). Isi `MAIL_*` di `.env` produksi setelah diputuskan.
