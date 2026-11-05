# Akhiri sesi lain saat password diganti atau di-reset (F-07)

Sumber: temuan F-07 di `docs/reviews/2026-11-02-full-code-review.md`.

## Masalah

`PasswordController::update` (ganti password dari profil) tidak mengakhiri sesi di perangkat lain. `NewPasswordController::store` (reset lewat email) merotasi `remember_token`, tetapi baris di tabel `sessions` tetap hidup. Bila password diganti karena akun dicurigai bocor, sesi penyerang tetap aktif sampai kedaluwarsa (120 menit, diperpanjang tiap aktivitas). Fitur "keluarkan perangkat lain" sudah ada di `ProfileSessionController`, tetapi hanya manual.

## Rancangan

Logika pengakhiran sesi dipindah dari `ProfileSessionController` ke `App\Domains\Access\Services\SessionService` dan dipakai tiga tempat:

- `endOthers(User, Request, string $password)`: putar `remember_token`, `Auth::logoutOtherDevices($password)` (memasang ulang cookie "Ingat saya" perangkat ini dengan token baru), lalu hapus baris `sessions` milik pengguna selain sesi saat ini. Dipakai `ProfileSessionController` (perilaku tetap) dan `PasswordController::update` (sesudah password tersimpan, dengan password baru).
- `endAll(User)`: putar `remember_token` dan hapus semua baris `sessions` milik pengguna. Dipakai `NewPasswordController::store` di dalam callback reset yang berhasil (pelaku belum tentu masuk di perangkat ini, dan sesi lama mungkin milik penyerang).

Rotasi `remember_token` di callback reset dipindah ke `endAll` agar tidak ganda.

Tidak diubah: `ProfileSessionController` tetap menolak (501) bila driver sesi bukan `database`. Ganti dan reset password tidak menolak: pada driver lain penghapusan baris tidak berefek, jadi produksi wajib memakai `SESSION_DRIVER=database` (sudah bawaan `.env.example`; dicatat di panduan deployment).

Di luar cakupan: pengakhiran sesi saat akun dinonaktifkan (sudah ditangani `EnsureAccountIsActive` pada permintaan berikutnya), dan aktivasi akun baru (belum ada sesi sebelumnya).

## Pengujian

Pest `tests/Feature/Auth/SessionRevocationTest.php`: ganti password mengeluarkan perangkat lain dan memutar token, sesi saat ini dan sesi orang lain tetap; password lama salah tidak mengeluarkan siapa pun; reset mengakhiri semua sesi pengguna itu saja; token reset salah tidak mengakhiri sesi apa pun. Tes `ProfileSessionTest` yang ada tetap lulus tanpa diubah.
