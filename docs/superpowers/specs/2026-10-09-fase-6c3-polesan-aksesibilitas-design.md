# Fase 6c-3 — Polesan UI dan aksesibilitas: desain

Tanggal: 2026-10-09. Bagian ketiga dan terakhir dari 6c (6c-1 hardening dan 6c-2 ketahanan selesai). Rilis ditahan sampai 6c selesai. Dasarnya adalah audit atas build produksi (2026-10-09): Lighthouse, uji keyboard asli, pemindaian target sentuh, dan pemeriksaan bundle.

**Tujuan:** menutup celah aksesibilitas dan konsistensi yang tidak tertangkap alat otomatis, tanpa mengubah alur fitur.

**Sukses:** pengguna keyboard dapat melompati sidebar dengan satu Tab; fokus keyboard terlihat jelas dan seragam di semua tautan dan kontrol; pembaca layar mendapat pengumuman saat halaman berganti dan nama pada input cari dan tabel; semua target sentuh teks minimal 24 px; halaman pemilih siswa jauh lebih pendek di mobile; sistem tidak terindeks mesin pencari.

**Hasil audit (kondisi awal):** Lighthouse aksesibilitas 100 dan praktik terbaik 100 pada login, beranda, pengguna, dan siswa (kontras lolos); landmark, hierarki heading, `lang="id"` (dari `.env`), dan `prefers-reduced-motion` sudah baik; inti JS sekitar 200 kB gzip didominasi inti Inertia sehingga tidak ada pengoptimalan bundle yang layak.

**Di luar cakupan:** mode gelap, pengoptimalan bundle, komponen baru, SEO selain `noindex`.

**Keputusan (dikonfirmasi user, 2026-10-09):** sembilan temuan A1–E1 semuanya masuk, termasuk perpindahan fokus saat navigasi (A2).

## 1. Navigasi keyboard dan SPA

- **A1 — Lewati ke konten:** `AppLayout` memuat tautan `<a href="#konten">Lewati ke konten</a>` sebagai elemen fokus pertama (`sr-only`, tampil saat fokus dengan kelas `focus:not-sr-only` dan gaya cincin yang sama dengan tombol). `<main>` mendapat `id="konten"` dan `tabindex="-1"` (tanpa outline saat difokuskan lewat kode).
- **A2 — Fokus dan pengumuman saat navigasi:** composable `resources/js/composables/useRouteFocus.ts` (pola `attach...(router?)` yang diuji dengan router palsu, seperti `useLoadFailure`), dipasang sekali di `app.ts`. Pada peristiwa `navigate`, bila `page.component` berbeda dari halaman sebelumnya: fokus dipindah ke `#konten` (`preventScroll: true`) setelah DOM diperbarui, dan sebuah wilayah `role="status"` `sr-only` di `AppLayout` diisi "Halaman: {judul dokumen}". Tidak ada perpindahan fokus pada muat awal, partial reload, filter yang diketik, atau kunjungan ke komponen yang sama (supaya kolom cari tidak kehilangan fokus).
- **A3 — Cincin fokus seragam:** satu aturan global di `resources/css/app.css` untuk `a:focus-visible` (outline 2 px `--ring`, offset 2 px, radius kecil) menggantikan cincin bawaan browser yang tipis; komponen yang sudah memakai kelas `focus-visible:ring-*` tidak berubah.

## 2. Label dan semantik

- **B1 — `SearchInput`:** `inheritAttrs: false` dan `$attrs` diteruskan ke `Input` sehingga `aria-label` (mis. dari `StudentMultiSelect`) mendarat di input; `id` tetap diteruskan seperti sekarang.
- **B2 — Tabel:** `TableHead` memberi `scope="col"` secara bawaan. `DataTable` menerima prop opsional `label` yang menjadi `aria-label` pada `<table>` (komponen `Table` meneruskan atribut ke `<table>`). Semua 12 pemakaian `DataTable` diberi `label` yang menyebut isi tabel ("Daftar jadwal", "Peserta jadwal", "Siswa sudah ditandai", "Rekap pendaftaran per sekolah", "Log aktivitas", "Daftar pendaftaran", "Entri pendaftaran", "Daftar pengguna", "Daftar role", "Daftar sekolah", "Daftar siswa", "Antrean verifikasi").
- **B3 — Locale bawaan:** `config/app.php` memakai `env('APP_LOCALE', 'id')` sehingga `lang="id"` tetap benar bila `.env` produksi tidak mengisinya.

## 3. Target dan tata letak

- **C1 — Target sentuh 24 px:** tautan judul di baris tabel (Jadwal, Siswa, Pendaftaran, Pengguna, Verifikasi, Show jadwal) dan tautan pada `Breadcrumbs` mendapat `py-0.5` atau `inline-flex min-h-6 items-center` sehingga tinggi minimal 24 px tanpa mengubah tinggi baris tabel yang sudah ditetapkan.
- **C2 — Alasan "belum eligible" ringkas:** di `StudentMultiSelect`, daftar poin diganti satu paragraf `Belum memenuhi: {alasan1} · {alasan2}` (teks alasan tetap sama, tetap dibaca sebagai teks), diikuti tautan "Lengkapi dokumen". Tes yang ada tetap memeriksa teks alasan.

## 4. Indeksasi

- **E1 — Tidak terindeks:** `<meta name="robots" content="noindex, nofollow">` di `app.blade.php`, header `X-Robots-Tag: noindex, nofollow` dari `SecurityHeaders` (mencakup halaman error dan berkas), dan `public/robots.txt` menjadi `Disallow: /`.

## 5. Pengujian dan verifikasi

- **Vitest:** `useRouteFocus` (fokus dan pengumuman hanya saat komponen berubah; tidak pada muat awal atau partial reload; pelepas pendengar), `SearchInput` (atribut `aria-label` mendarat di input), `DataTable` (`aria-label` dari `label`, `th` ber-`scope="col"`), `StudentMultiSelect` (alasan ditampilkan sebagai satu baris ringkas).
- **Pest:** `SecurityHeadersTest` mendapat asersi `X-Robots-Tag` pada halaman biasa dan error; suite penuh tetap hijau.
- **Verifikasi tambahan:** `php artisan tinker` dengan `APP_LOCALE` kosong memastikan `config('app.locale')` bernilai `id`.
- **Browser (build produksi, 1440 dan 390 px):** Tab pertama menyorot "Lewati ke konten" dan Enter memindahkan fokus ke konten; pindah halaman memindahkan fokus ke `main` dan wilayah status berisi judul baru, sementara mengetik di kolom cari tidak kehilangan fokus; cincin fokus tampak jelas pada tautan tabel dan breadcrumb; pemindaian target tidak lagi menemukan tautan di bawah 24 px; pemilih siswa di mobile jauh lebih pendek; Lighthouse tetap 100 dan SEO naik; konsol bersih.
- **Review:** `ponytail:ponytail-review` untuk semua perubahan dan `impeccable` untuk frontend, sebelum klaim selesai.

## 6. Catatan teknis

- Prop halaman Inertia tidak boleh bernama `auth`, `flash`, `menu`, `notifications`; jangan matikan Vite dev server milik user (`public/hot` hanya dipindah sementara untuk uji build produksi lalu dikembalikan); bila klik asli Playwright tidak menghasilkan event jalankan `browser_close`; skrip Python dengan backslash namespace PHP gagal (pakai Edit/Write); `npx prettier --write` pada file FE yang diubah; panduan UI diperbarui (bagian 6 pola lintas halaman: skip link, fokus saat navigasi, cincin fokus, target 24 px).
