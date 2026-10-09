# Fase 6c-1 — Hardening keamanan dan data: desain

Tanggal: 2026-10-09. Bagian pertama dari tiga sub-fase 6c (6c-1 hardening, 6c-2 ketahanan, 6c-3 polesan UI dan aksesibilitas). Rilis ditahan sampai 6c selesai. Dasarnya adalah audit kondisi saat ini (2026-10-09) terhadap sprint plan Fase 6 "Hardening: tinjauan keamanan (permission semua route, rate limit, header keamanan, validasi unggah), indeks database, performa daftar besar".

**Tujuan:** menutup celah yang tersisa tanpa mengubah perilaku fitur: header keamanan HTTP, batas laju menyeluruh, jaminan otomatis bahwa setiap rute dijaga, bukti bahwa unggahan berbahaya ditolak, indeks yang dibutuhkan, dan jaminan bahwa daftar tidak melakukan N+1.

**Sukses:** semua respons web membawa header keamanan; CSP diberlakukan di luar lingkungan `local` tanpa memecah tampilan (diverifikasi di browser dengan build produksi); rute baru tanpa penjaga membuat tes gagal; berkas unggah yang menyamar atau terlalu besar ditolak (tes); jumlah query tiap halaman daftar tidak tumbuh menurut jumlah baris (tes).

**Temuan audit (kondisi awal):**
- Tidak ada middleware header keamanan; `app.blade.php` memuat font dari `fonts.bunny.net` dan `@routes` (Ziggy) berupa skrip inline.
- `throttle` ada pada aksi tulis tertentu (login 6/mnt, impor 6/mnt, ekspor 20/mnt, sisanya 30–60/mnt) tetapi tidak pada semua rute (mis. PUT/DELETE pengguna, role, sekolah, siswa; semua GET).
- Tes izin rute (`RoutePermissionMatrixTest`) memeriksa daftar yang ditulis tangan; rute baru yang lupa `can:` tidak terdeteksi.
- Validasi unggah sudah ada (`mimes:pdf,jpg,jpeg,png` maks 5 MB untuk dokumen; `mimes:xlsx` maks 2 MB untuk impor; nama berkas UUID) tetapi tidak ada tes untuk berkas menyamar atau terlalu besar.
- `activity_log` berindeks `log_name`, `subject`, `causer`, tetapi tidak `created_at`, padahal halaman Log menyaring rentang waktu.
- `.env.example` bernilai `APP_DEBUG=true`; `SESSION_SECURE_COOKIE` kosong. Keduanya konfigurasi produksi.

**Di luar cakupan:** `APP_DEBUG=false`, `SESSION_SECURE_COOKIE`, trusted proxy/host, SMTP, dan supervisor/cron (bagian rilis, ditahan); self-host font; penutupan CSP `style-src 'unsafe-inline'` (ditolak karena reka-ui dan Sonner menyisipkan gaya inline); WAF atau fail2ban.

**Keputusan (dikonfirmasi user, 2026-10-09):** pemecahan 6c-1/6c-2/6c-3; desain hardening disetujui lengkap termasuk CSP bernonce di luar `local`; impor Excel lewat queue dikerjakan di 6c-2.

## 1. Header keamanan

Middleware baru `App\Http\Middleware\SecurityHeaders`, ditambahkan di grup `web` (setelah `HandleInertiaRequests`).

- **Selalu:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- **HSTS:** `Strict-Transport-Security: max-age=31536000; includeSubDomains` hanya bila `$request->secure()`.
- **CSP:** hanya bila `! app()->isLocal()` (kunci konfigurasi `security.csp`, default aktif di luar local, dapat dimatikan lewat `.env` untuk darurat). Kebijakan:
  `default-src 'self'; script-src 'self' 'nonce-{nonce}'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'`.
- **Nonce:** dibuat per request (`Vite::useCspNonce()` memberi nonce yang sama ke tag skrip/gaya `@vite`); `app.blade.php` memakai `@routes(nonce: Vite::cspNonce())`. Skrip JSON Inertia (`data-page`) bukan skrip yang dieksekusi sehingga tidak butuh nonce.
- Respons error Inertia (403/404/500) dan unduhan berkas ikut membawa header (middleware grup web).

## 2. Batas laju menyeluruh

`RateLimiter::for('web', ...)` di `AppServiceProvider` dengan batas 240 permintaan per menit per pengguna (kunci `user:{id}`, tamu: IP), dipasang sebagai `throttle:web` di grup `web`. Limit khusus yang lebih ketat tetap berlaku di atasnya. Melebihi batas menghasilkan 429 (halaman error bergaya aplikasi bila status didukung; bila belum, bawaan Laravel).

## 3. Tes audit rute struktural

`tests/Feature/Access/RouteGuardAuditTest.php` menelusuri `Route::getRoutes()` dan menegaskan:
- Setiap rute bernama atau tidak (kecuali pengecualian) memiliki salah satu middleware `auth` atau `guest`, atau berada di daftar pengecualian publik bernama: `aktivasi.show`, `aktivasi.store`, `up`, rute fallback 404, dan rute internal framework (`storage.*`, `ziggy`).
- Setiap rute ber-`auth` memiliki middleware `can:` atau berada di daftar pengecualian akun-sendiri bernama: `dashboard`, `profile.*`, `password.update`, `logout`, `notifikasi.*`, `siswa.dokumen.*` bila dijaga di service (diverifikasi saat menulis rencana), dan rute verifikasi email yang tidak dipakai tidak ada.
- Daftar pengecualian dikunci sebagai konstanta di tes sehingga menambah pengecualian terlihat di diff.

## 4. Tes unggah

Pada rute dokumen siswa dan impor: berkas dengan ekstensi tidak diizinkan (`.exe`, `.php`, `.svg`) ditolak 422; berkas bernama `.pdf` yang isinya bukan PDF ditolak; berkas lebih dari batas ukuran ditolak; berkas yang diterima tersimpan dengan nama UUID tanpa nama asli dan di disk privat (tidak dapat diakses lewat URL publik).

## 5. Indeks dan performa daftar

- Migrasi `add_created_at_index_to_activity_log`: indeks `created_at`.
- Tes `tests/Feature/Performance/ListQueryCountTest.php`: untuk `/pengguna`, `/role`, `/sekolah`, `/siswa`, `/pendaftaran`, `/verifikasi`, `/jadwal`, `/log`, `/notifikasi`, dan `/laporan` hitung jumlah query dengan 3 baris data lalu dengan 23 baris data (satu halaman penuh); jumlah query pada data besar tidak boleh melebihi jumlah pada data kecil. Temuan N+1 diperbaiki dengan eager loading (`with`) di repository terkait sebagai bagian dari rencana, bukan dibiarkan.
- Hasil `EXPLAIN` tidak menjadi tes (lunak menurut versi MySQL); dicatat sebagai bagian verifikasi manual rencana bila ada kueri yang mencurigakan.

## 6. Pengujian dan verifikasi

- **Header:** tes fitur memeriksa header pada halaman biasa, halaman error, dan unduhan; CSP hadir saat lingkungan bukan `local` dan hilang saat `local`; HSTS hanya pada HTTPS; nonce di header sama dengan nonce pada tag skrip dan berbeda antar-request.
- **Limiter:** tes memakai batas diperkecil lewat konfigurasi untuk membuktikan 429 pada permintaan melebihi batas.
- **Browser:** jalankan build produksi dengan `APP_ENV=production` (dan `public/hot` dipindahkan sementara lalu dikembalikan) lalu periksa Login, Beranda, Pengguna, Jadwal (dialog), Laporan, dan Verifikasi pada 1440 dan 390 px: tidak ada pelanggaran CSP di konsol, font, ikon, dialog, toast, dan menu berfungsi.
- **Review:** `ponytail:ponytail-review` untuk semua perubahan; `impeccable` hanya bila ada perubahan tampilan.

## 7. Catatan teknis

- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`; dataset Pest tanpa closure; tes yang memicu notifikasi membuat permission; suite penuh BE lebih dari 120 detik (background); helper tes global bernama unik.
- Jangan mematikan Vite dev server milik user (port 5173); `public/hot` hanya dipindahkan sementara dan dikembalikan.
- Catat di memory item konfigurasi produksi: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, trusted proxy, dan memastikan `APP_ENV=production` agar CSP aktif.
