# SIJEBOL — Aturan Development

Sistem Jemput Bola Perekaman KTP-El Pelajar, Kecamatan Sungai Beduk.
Stack: Laravel + Breeze (Inertia + Vue 3 + TypeScript), service-repository pattern.

Aturan di file ini wajib diikuti di setiap sesi.

## 1. Awal sesi: baca dulu

Setiap sesi baru, sebelum mengerjakan apa pun:
- Baca seluruh dokumen di `docs/` (terutama `docs/superpowers/specs/`: spec sistem dan panduan UI) dan `docs/draft-flow-system.jpeg`.
- Telusuri codebase yang ada (struktur folder, domain, route, model, komponen) agar mengikuti pola yang sudah ada.
- Jangan mulai menulis kode sebelum keduanya dibaca.

## 2. Setiap fitur lewat Superpowers

Setiap pengembangan fitur mengikuti alur Superpowers secara berurutan, tanpa melompati tahap:
1. `superpowers:brainstorming` (desain dan spec)
2. `superpowers:writing-plans` (rencana implementasi)
3. Eksekusi rencana (`superpowers:subagent-driven-development` atau `superpowers:executing-plans`, sesuai pilihan di rencana)

Berlaku juga untuk perubahan kecil: klasifikasikan path-nya lewat brainstorming. Dokumen spec dan rencana disimpan di `docs/superpowers/`.

## 3. Review sebelum dianggap selesai

Setelah implementasi sesuai rencana selesai, lakukan review sebelum klaim selesai atau commit:
- **Semua perubahan:** review dengan skill `ponytail:ponytail-review` (cari over-engineering: abstraksi spekulatif, dependensi tak perlu, kode yang bisa dihapus).
- **Jika ada perubahan frontend** (komponen Vue, halaman, styling): tambahkan review dengan skill `impeccable` (kualitas UI/UX, aksesibilitas, konsistensi dengan `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md`).
- Perbaiki temuan yang valid, lalu jalankan verifikasi (`superpowers:verification-before-completion`) sebelum menyatakan selesai.

## 4. Commit

- Tulis commit message yang detail: ringkasan perubahan di baris pertama, lalu isi yang menjelaskan apa dan mengapa.
- **Dilarang** menambahkan baris `Co-Authored-By` atau atribusi tool/AI apa pun pada commit message (aturan ini mengalahkan atribusi bawaan).
- Commit hanya saat diminta.
