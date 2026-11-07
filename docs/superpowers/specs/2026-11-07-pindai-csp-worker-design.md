# Pindai tiket di desktop: CSP memblokir worker pemindai

## Masalah

Dilaporkan: QR tidak terbaca bila dipindai dari laptop atau webcam eksternal; di HP normal.

## Penyebab (terbukti di Chrome 7 Nov 2026)

`qr-scanner` memakai `BarcodeDetector` bawaan peramban bila ada (Chrome Android, macOS). Di Chrome Windows/Linux dan di Safari/Chrome iOS API itu tidak ada, sehingga pustaka membuat worker JavaScript dari **Blob URL** (`new Worker(URL.createObjectURL(new Blob(...)))`).

CSP kita (`SecurityHeaders::csp()`) hanya punya `default-src 'self'` dan `script-src 'self' 'nonce-…'`, tanpa `worker-src`. Worker jatuh ke `script-src`, dan `blob:` tidak diizinkan, sehingga peramban menolak membuat worker dan dekode tidak pernah berjalan.

Bukti: `QrScanner.scanImage` pada QR uji di Chrome headless (tanpa `BarcodeDetector`): tanpa CSP terbaca; dengan CSP SIJEBOL gagal `Scanner error: [object Event]` dan konsol melaporkan pelanggaran `worker-src`/`script-src`.

CSP tidak aktif di lingkungan `local` (`cspEnabled()`), jadi pengujian lokal selalu lolos; masalah hanya muncul di UAT dan produksi.

Kegagalan senyap: `Pindai.vue` tidak memberi `onDecodeError`, sehingga galat mesin pemindai hanya masuk `console.log`. Kamera tampak menyala tetapi tidak ada yang terbaca dan tidak ada pesan.

## Keputusan (pemilik, 7 Nov 2026: "gas perbaiki keempatnya")

1. CSP: tambah `worker-src 'self' blob:`.
2. `Pindai.vue`: tangani `onDecodeError`; galat mesin (bukan "tidak ada QR di frame") menghentikan kamera dan menampilkan pesan dengan jalan keluar ke penandaan manual.
3. Tes: `SecurityHeadersTest` memeriksa `worker-src`; Vitest memeriksa penanganan galat mesin.
4. Pemilik menguji di UAT dengan laptop dan webcam.

## Rancangan

- `worker-src 'self' blob:` berlaku untuk **semua** halaman, bukan hanya `schedules.scan.show`. Alasan: Inertia berpindah halaman tanpa memuat ulang dokumen, sehingga CSP yang berlaku adalah milik halaman yang dimuat pertama (mis. dasbor). Petugas yang masuk ke Pindai lewat tautan Inertia tidak akan mendapat CSP halaman Pindai. (Berbeda dengan Turnstile, yang dimuat lewat tautan langsung.)
- Risiko pelonggaran sempit: `worker-src` hanya mengatur pembuatan worker, bukan eksekusi skrip di halaman; `script-src` tetap `'self' 'nonce-…'`. Blob hanya bisa dibuat oleh skrip yang sudah lolos `script-src`.
- `onDecodeError`: abaikan `QrScanner.NO_QR_CODE_FOUND` (terjadi tiap frame tanpa QR); galat lain → `disposeScanner()`, `state = 'idle'`, `cameraError` berisi pesan bahwa pemindai tidak dapat memproses gambar di peramban ini, disertai saran peramban lain atau penandaan manual. Memakai jalur `cameraError` yang sudah ada (InlineAlert + tautan "Tandai kehadiran manual").
- Catatan hasil uji: bila worker diblokir, pustaka tidak melaporkan galat segera; ia menunggu batas waktu 10 detik per frame lalu memanggil `onDecodeError('Scanner error: timeout')`. Jadi pesan baru muncul ±10 detik setelah kamera menyala. Diterima: yang penting tidak senyap lagi dan ada jalan keluar manual.
- Penjaga `if (!scanner) return` agar frame yang masih berjalan setelah pembuangan tidak menimbulkan pesan ganda.

## Di luar cakupan
- Faktor webcam (resolusi, silau layar HP) dan ukuran QR tiket: dinilai setelah worker berjalan, di UAT.
- Mengganti pustaka pemindai.
- iPhone/iOS: diduga kena masalah yang sama dan ikut tertutup perbaikan ini; perlu dicek pemilik bila ada perangkat.

## Pengujian
- Pest: CSP memuat `worker-src 'self' blob:` pada halaman biasa.
- Vitest: galat mesin menghentikan pemindai, menampilkan pesan, dan menyisakan tautan manual; `NO_QR_CODE_FOUND` diabaikan.
- Bukti peramban (Chrome headless, kamera palsu): CSP lama → `onDecodeError('Scanner error: timeout')` setelah ±10 detik dan pelanggaran CSP di konsol; CSP baru → dekode `scanImage` berhasil (`SIJEBOL-TIKET-UJI-12345`) dan hanya `NO_QR_CODE_FOUND` pada video tanpa QR.
