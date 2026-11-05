# Detail sesi rekaman: tampilan HP (`Jadwal/Show.vue`)

## Masalah

Dilaporkan: halaman detail sesi (`/schedules/{id}`) tidak menyesuaikan layar HP. Diukur di Chrome headless (360 dan 390 px, data lokal): seluruh halaman bisa digeser menyamping (lebar 446 px). Penyebabnya baris tombol header (Ubah, Pindai tiket, Penandaan rekaman, Batalkan sesi, menu `⋯`) yang dipaksa satu baris dengan `flex-1`; "Penandaan rekaman" keluar layar 56 px. Selain itu, tabel Peserta dipaksa selebar minimal 560 px, sehingga di HP hanya kolom Siswa yang terlihat dan kolom Status harus digeser.

## Keputusan (pemilik, 5 Nov 2026)

- Header HP: **grid 2 kolom**.
- Tabel Peserta di HP: **status di bawah nama**.
- Hasil critique impeccable (28/40): perbaiki dua P2 dan tambahkan jumlah peserta; `Jadwal/Index` dan `Rekaman` dibiarkan sebagai tabel geser (kolomnya lebih banyak, sesuai §5.25 dan §5.27).

## Rancangan

- Grup tombol header: `grid grid-cols-2 gap-2` di HP, `sm:flex` dari 640 px. "Penandaan rekaman" `col-span-2` (satu baris penuh); tombol tunggal juga penuh (`[&>*:only-child]:col-span-2`); menu `⋯` dibungkus `flex justify-end` agar rata kanan.
- Tabel: kolom Status `hidden md:table-cell`; badge status ditampilkan di bawah NISN pada sel Siswa dengan `md:hidden`; tabel `md:min-w-[560px]`; petunjuk "Geser tabel" dihapus.
- Nama siswa: `break-words whitespace-normal md:truncate`. `whitespace-normal` wajib karena sel tabel shadcn memakai `whitespace-nowrap`.
- Judul "Peserta (N)".
- Panduan UI §5.26 diperbarui.

## Di luar cakupan
- Pola tabel `Jadwal/Index` dan `Jadwal/Rekaman`.
- Memindahkan aksi lapangan (Pindai tiket) ke bilah tetap di bawah layar.
- Cari/saring peserta.

## Pengujian
Vitest (`Jadwal.test.ts`, blok "tampilan HP"): kelas grid dan span pada header, tombol tunggal penuh, kolom Status hanya `md`, badge HP di bawah nama, nama `break-words whitespace-normal md:truncate`, judul memuat jumlah. jsdom tidak menghitung CSS, jadi kelas diuji sebagai maksud; buktinya pengukuran di peramban nyata: tanpa overflow horizontal di 360, 390, 768, dan 1440 px pada sesi lampau, mendatang berpeserta (kolom Aksi), mendatang kosong, dan dibatalkan, termasuk nama 60 karakter yang turun tiga baris. Satu kesalahan tertangkap lewat pemeriksaan peramban, bukan tes: `break-words` saja tidak cukup karena `whitespace-nowrap` pada sel.
