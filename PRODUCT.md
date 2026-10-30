# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Siswa** (SMA/SMK/MA, umur 16 tahun ke atas): mendaftar akun lewat link per sekolah, melengkapi data dan dokumen sendiri, mendaftar sesi rekaman, membawa tiket QR. Dominan memakai HP, sering di sela jam sekolah.
- **Operator sekolah**: hanya memantau siswa dan sesi di sekolahnya sendiri.
- **Admin kecamatan** (staf Kec. Sungai Beduk): memverifikasi pendaftaran, membuat sesi rekaman per sekolah, memindai tiket QR, mengelola pengguna dan sekolah. Dominan memakai desktop; pemindaian lapangan memakai HP.

## Product Purpose

SIJEBOL (Sistem Jemput Bola Perekaman KTP-El Pelajar) untuk Kecamatan Sungai Beduk: petugas datang ke sekolah pada sesi berkuota, siswa yang memenuhi syarat direkam KTP-el-nya. Sukses = siswa eligible terdaftar ke sesi, terverifikasi, hadir, dan tercatat sudah rekaman tanpa pengurusan manual.

## Positioning

Layanan pemerintah kecamatan yang menjemput bola ke sekolah, dengan alur tunggal dari akun siswa sampai tiket QR dan pemindaian kehadiran.

## Operating Context

- Satu akun satu role; izin diatur lewat RBAC (spatie permission), bukan hardcode per role.
- Akun siswa hanya melihat datanya sendiri; role terikat sekolah hanya melihat sekolahnya.
- Pendaftaran akun siswa lewat link per sekolah; akun staf dibuat admin lewat link aktivasi.
- Notifikasi lewat lonceng dalam aplikasi dan email; WhatsApp ditunda.
- Data siswa terkunci selama pendaftarannya diproses.
- Sistem belum rilis; masih tahap pengembangan tanpa pengguna sungguhan.

## Capabilities and Constraints

- Stack: Laravel + Inertia + Vue 3 + TypeScript, komponen shadcn-vue (`@/Components/ui`) dan komponen shared project (`FormField`, `PageHeader`, `PasswordInput`, `NewPasswordFields`).
- Bahasa antarmuka: Indonesia. Zona waktu Asia/Jakarta. Format tanggal lewat `lib/date.ts`.
- NIK selalu dimasker di tampilan dan log.
- Sesi disimpan di database (`SESSION_DRIVER=database`); email lewat Resend; queue lewat database.
- Profil saat ini hanya mengubah nama dan nomor HP (format 08, 10-14 digit) dan password; email dan role baca saja.

## Brand Commitments

Panduan visual sudah ada dan mengikat: `docs/superpowers/specs/2026-10-07-sijebol-ui-guide.md` (administratif, bersih, resmi; canvas abu-putih, biru hanya untuk sidebar dan aksen; tanpa gradien; tanpa mode gelap).

## Evidence on Hand

Spec sistem dan fase di `docs/superpowers/specs/`. Tidak ada testimoni, metrik penggunaan, atau data pengguna nyata; jangan dikarang.

## Product Principles

1. Jalan buntu harus selalu punya jalan keluar tertulis (siapa dihubungi, apa langkah berikutnya).
2. Status tidak pernah hanya dibedakan lewat warna; selalu ada teks.
3. Tampilkan hanya yang boleh dilihat dan diubah pengguna itu; tanpa izin berarti tidak muncul.
4. Utamakan HP untuk siswa dan lapangan, desktop untuk pekerjaan admin.

## Accessibility & Inclusion

Semua kontrol dapat dioperasikan dengan keyboard, label terhubung ke input, area sentuh minimal 40 px (24 px untuk tautan baris), kontras teks minimal 4.5:1, pengumuman halaman dan fokus saat navigasi (lihat §6 panduan UI).
