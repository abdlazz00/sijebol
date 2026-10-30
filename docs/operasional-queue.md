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

## SMTP

Penyedia SMTP belum ditentukan (spec v1 bagian 11). Isi `MAIL_*` di `.env` produksi setelah diputuskan.

## Perintah terjadwal (fase 7c dan 7d)

Scheduler menjalankan tiga perintah **setiap jam** (`withoutOverlapping`). Keduanya butuh cron `schedule:run` setiap
menit (lihat di atas) dan worker antrean yang hidup, karena pemberitahuan ke siswa dikirim lewat antrean.

| Perintah | Fungsi |
|---|---|
| `php artisan sijebol:kedaluwarsakan` | Pendaftaran **Perlu perbaikan** yang lewat batas waktu, dan pendaftaran **Menunggu verifikasi / Perlu perbaikan** pada sesi yang tanggalnya sudah lewat, menjadi **Kedaluwarsa** (aktor "Sistem"). Kursi dilepas dan siswa diberi tahu. Aman dijalankan berulang. Kode keluar 1 bila ada pendaftaran yang gagal diproses (lihat log). |
| `php artisan sijebol:tandai-tidak-hadir` | Pendaftaran **Terdaftar** pada sesi yang tanggalnya sudah lewat (mulai hari setelah sesi) menjadi **Tidak hadir** (aktor "Sistem", catatan "Tidak terpindai sampai sesi berakhir."). Kursi dilepas; tanpa notifikasi. Status lain, termasuk Sudah rekaman, tidak disentuh, dan tiket yang dipindai di antara pemilihan dan pemrosesan tidak ditimpa. Aman dijalankan berulang. Kode keluar 1 bila ada pendaftaran yang gagal diproses (lihat log). |
| `php artisan sijebol:kirim-pengingat` | Pengingat batas perbaikan (tenggat tinggal 24 jam atau kurang) dan pengingat sesi H-1 (dikirim sejak pukul 08.00 WIB, `config('sijebol.reminder_hour')`). Tiap pendaftaran diingatkan sekali. |

Untuk uji lokal jalankan keduanya langsung dari terminal; `php artisan schedule:list` menampilkan jadwalnya.
