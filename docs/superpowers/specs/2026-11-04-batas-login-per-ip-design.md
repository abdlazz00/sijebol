# Batas percobaan login gagal per IP (F-08)

Sumber: temuan F-08 di `docs/reviews/2026-11-02-full-code-review.md`.

## Masalah

`LoginRequest::throttleKey` memakai `email|IP` dengan batas 5 kegagalan. Satu IP bebas mencoba banyak email berbeda (password spraying: satu password umum ke banyak akun) hanya dibatasi batas umum 240 permintaan per menit.

## Keputusan (pemilik, 4 Nov 2026)

- Batas **kegagalan** login per IP: **50 per 10 menit**, dapat diubah lewat env.
- `trustProxies('*')` **tidak diubah**, didokumentasikan.

## Rancangan

**Limiter per IP.** `LoginRequest` menambah kunci `login-ip|<ip>`:
- Setiap login gagal (`Auth::attempt` salah, akun nonaktif, belum aktif, email tak dikenal) menambah hitungan, dengan jendela `security.login_ip_window` (bawaan 600 detik).
- `ensureIsNotRateLimited` menolak bila `email|IP` melewati 5 **atau** hitungan IP melewati `security.login_ip_failures` (bawaan 50), dengan pesan `auth.throttle` dan event `Lockout` (tercatat sebagai `login.terkunci`).
- Login sukses **tidak** menambah hitungan dan **tidak** mereset hitungan IP: penyerang tidak boleh membersihkannya dengan login ke akun miliknya, dan satu kelas di Wi-Fi sekolah yang berhasil login bersamaan tidak terhitung.
- Hanya kegagalan yang dihitung, karena siswa satu sekolah berbagi IP (lihat F-29).

**Mengapa 50 per 10 menit.** Rata-rata 5 gagal per menit per IP: satu kelas yang salah ketik masih muat, sedangkan spraying dibatasi sekitar 300 percobaan per jam per IP. Angka konservatif dan dapat diubah (`SECURITY_LOGIN_IP_FAILURES`, `SECURITY_LOGIN_IP_WINDOW`).

**trustProxies.** Di Laravel, `'*'` hanya mempercayai peer yang terhubung langsung (Nginx Proxy Manager), lalu membaca `X-Forwarded-For` dari kanan; header yang disisipkan klien di sebelah kiri diabaikan. `docker-compose.prod.yml` tidak mempublikasikan port, jadi kontainer hanya terjangkau lewat jaringan Docker. Tidak diubah. Syaratnya: IP yang sampai ke aplikasi harus IP pengunjung asli, bukan IP edge Cloudflare atau NPM. Bila tidak, semua pengunjung berbagi satu IP dan setiap limiter per IP (termasuk `throttle:5,1` pendaftaran siswa) menghantam semua orang. Pengecekannya (kueri atas kolom `sessions.ip_address`) ada di panduan deployment.

## Di luar cakupan
- Batas per email lintas IP (serangan terdistribusi ke satu akun): ditolak karena membuka kuncian akun korban oleh penyerang (DoS).
- Batas serupa untuk Lupa password (sudah dibatasi `throttle:web` dan pesan seragam).

## Pengujian
Pest `tests/Feature/Auth/LoginIpThrottleTest.php` (batas diperkecil lewat config): kegagalan dengan email berbeda mengunci IP termasuk untuk kredensial benar dan tercatat; IP lain tidak terkunci; sepuluh login sukses beruntun tidak terkunci; login sukses tidak mereset hitungan; hitungan kedaluwarsa sesudah jendela. Tes login yang ada tetap lulus.
