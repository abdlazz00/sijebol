# Pengaman pendaftaran akun siswa (F-03)

Sumber: temuan F-03 di `docs/reviews/2026-11-02-full-code-review.md`.

## Masalah

`POST /register/{token}` bersifat publik. Setiap permintaan untuk email baru atau yang belum aktivasi membuat akun siswa dan mengirim email, dan `ActivationService::issue()` mencabut link sebelumnya. Satu-satunya pembatas di server adalah `throttle:5,1` per IP; cooldown "Kirim ulang" hanya ada di sisi klien. Akibatnya:

1. Kuota Resend (gratis 100 email/hari) bisa dihabiskan dari satu IP (sekitar 300 permintaan per jam), sehingga email aktivasi staf dan reset password yang sah ikut gagal.
2. Satu alamat email dapat dibanjiri email.
3. Link aktivasi korban terus diganti (korban tetap menerima email terbaru yang valid, jadi dampaknya membingungkan, bukan mengunci).
4. Akun siswa belum aktif menumpuk dari email acak.

## Keputusan (pemilik, 2026-11-02)

- Pendekatan: **batas per alamat email + Cloudflare Turnstile**. Tanpa batas per link sekolah, karena penyerang dapat sengaja menghabiskannya untuk mengunci satu sekolah.
- Paket Resend: **gratis (100 email/hari)**. Kuota ini sendiri tidak cukup untuk ±1.500 siswa; dicatat terpisah sebagai F-29, bukan bagian perbaikan ini.

## Rancangan

### Batas per alamat email (server)

- Di `StudentSignupService::signup`, sesudah pemeriksaan "akun aktif / bukan siswa" (yang tidak mengirim apa pun), seluruh pembuatan akun dan pengiriman email dibungkus `RateLimiter::attempt` berkunci hash email, **maksimal 3 kali per 15 menit**.
- Permintaan di atas batas diabaikan diam-diam. Respons ke pengguna tetap sama (`sent`), supaya tidak membuka celah menebak email terdaftar.
- 3 kali cukup untuk siswa sah (kirim ulang setelah 60 detik di klien, dua kali) dan membatasi spam ke satu alamat menjadi 12 email per jam.

### Cloudflare Turnstile

- Konfigurasi `services.turnstile.site_key` dan `services.turnstile.secret` dari `TURNSTILE_SITE_KEY` dan `TURNSTILE_SECRET_KEY`.
- **Aktif hanya bila secret terisi.** Tanpa secret (lokal dan tes) pendaftaran berjalan seperti sebelumnya. Produksi dan UAT wajib mengisi keduanya (dicatat di panduan deployment).
- `App\Domains\Students\Support\TurnstileVerifier::passes(?string $token, ?string $ip): bool` memanggil `https://challenges.cloudflare.com/turnstile/v0/siteverify` (timeout 5 detik). Galat jaringan atau respons selain `success: true` dianggap gagal (**fail closed**: lebih baik pendaftaran tertunda daripada kuota terkuras).
- `StudentSignupRequest` menambah aturan `turnstile` (wajib dan lolos verifikasi) hanya saat aktif. Pesan galat: "Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi."
- `StudentSignupController::show` mengirim prop `turnstile_site_key` (null bila nonaktif).
- Token Turnstile sekali pakai, jadi frontend me-reset widget setelah setiap pengiriman, termasuk "Kirim ulang".
- Widget memakai `appearance: 'interaction-only'`: tak terlihat kecuali Cloudflare butuh interaksi. Satu widget dipasang di halaman untuk kedua keadaan (formulir dan "Periksa email").
- Tombol "Buat akun" dan "Kirim ulang" nonaktif sampai token tersedia, dengan keterangan "Memeriksa keamanan…". Galat `turnstile` ditampilkan di kedua keadaan.
- CSP: pada rute `register.*` dan hanya bila site key terisi, tambahkan `https://challenges.cloudflare.com` ke `script-src`, `frame-src`, dan `connect-src`.

### Di luar cakupan

- Batas per link sekolah (ditolak, lihat keputusan).
- Throttle per IP `5,1` tidak diubah. Catatan: siswa di Wi-Fi sekolah yang sama berbagi IP; bila ini terbukti mengganggu, naikkan batas itu (dicatat di F-29).
- Rencana kuota email (F-29).

## Pengujian

- Pest: verifier (sukses, gagal, galat jaringan, tanpa token), request (nonaktif tanpa secret, wajib token saat aktif), batas per email (4 permintaan, hanya 3 email, respons sama, tidak ada akun ganda), CSP rute daftar.
- Vitest: tombol nonaktif sampai token, token ikut terkirim, widget di-reset setelah kirim, tanpa site key perilaku lama.
- Cek peramban nyata dengan kunci uji Cloudflare (`1x00000000000000000000AA`) pada lingkungan yang menyalakan CSP.
