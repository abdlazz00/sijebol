# Halaman Profil untuk Semua Role

Tanggal: 2026-10-28. Status: menunggu persetujuan. Branch kerja: `feature/alur-mandiri-siswa` (atau cabang baru, keputusan user saat eksekusi).
Terkait: `2026-10-07-sijebol-ui-guide.md` (§5.15 Profil, sekarang usang), `2026-10-19-alur-mandiri-siswa-design.md`, `PRODUCT.md`.
Mockup yang disetujui: `.superpowers/brainstorm/1773-1793180292/content/profil-mockup.html` (tidak masuk git; dirangkum di §4).

## 1. Maksud

Halaman `/profile` sekarang hanya dua form (nama dan HP, ubah password) yang sama untuk semua role. Sejak alur mandiri siswa, pengguna paling banyak adalah siswa lewat HP, dan mereka tidak punya tempat melihat identitas akun, mengatur email notifikasi, mengamankan akun dari perangkat yang hilang, atau memperbaiki email yang salah ketik. Email yang terkunci tanpa jalan keluar adalah jalan buntu (prinsip produk 1).

Hasil yang diinginkan: satu halaman Profil yang memperlihatkan siapa pemilik akun menurut sistem, memberi kendali atas kontak, notifikasi, dan keamanan, dan tidak pernah membuntukan pengguna.

## 2. Tujuan, bukan tujuan, anti-tujuan

**Tujuan**
- Kartu identitas per role (baca saja), termasuk data siswa dan login terakhir.
- Ubah nama dan nomor HP (sudah ada, dipertahankan).
- Saklar email notifikasi.
- Ubah password (sudah ada, dipertahankan).
- "Keluar dari perangkat lain" dengan konfirmasi password.
- Ganti email dengan verifikasi.

**Bukan tujuan** (sengaja tidak dikerjakan)
- Foto profil, hapus akun, 2FA.
- Daftar perangkat aktif (browser, IP), preferensi notifikasi per kategori.
- Mengedit data siswa di Profil (tetap di `/me`; data terkunci saat pendaftaran diproses).
- Mengakhiri sesi lain otomatis saat ganti password.
- WhatsApp.
- Mode gelap.

**Anti-tujuan** (gagal walau tampak jalan)
- Menampilkan NIK utuh.
- Halaman yang menampilkan aksi atau data yang bukan milik role itu.
- Pengguna yang mematikan email lalu kehilangan tenggat tanpa peringatan.
- Ganti email yang berlaku sebelum email baru terbukti bisa menerima pesan.

## 3. Keputusan user

- Satu halaman `/profile` untuk semua role; bagian kiri (identitas) menyesuaikan role, bagian kanan sama.
- Susunan desktop: kartu ringkasan kiri + form kanan.
- Isi tambahan: login terakhir, data siswa baca-saja + tautan ke Data saya, preferensi email, keluar dari perangkat lain.
- Preferensi email: **satu saklar** "Kirim notifikasi ke email"; lonceng dalam aplikasi selalu aktif.
- Keluar dari perangkat lain: **satu tombol + konfirmasi password**, tanpa daftar perangkat.
- Email: **bisa diganti dengan verifikasi**.

## 4. Rancangan halaman

Mode Operate. Mengikuti panduan UI (token, kartu 8 px tanpa bayangan, tombol 40 px di form). Komponen yang ada dipakai ulang: `PageHeader`, `FormField`, `PasswordInput`, `NewPasswordFields`, `ConfirmDialog`, `StatusBadge`, `InfoList`, avatar inisial, `Input`, `Button`.

**Header:** "Profil", deskripsi "Kelola informasi akun dan keamanan Anda."

**Kartu identitas** (kiri, 320 px, melekat saat digulir)
- Avatar inisial 64 px, nama, lencana role, sekolah di bawahnya.
- Daftar label-nilai menurut role:

| Role | Baris |
|---|---|
| Siswa | NIK (dimasker lewat `App\Support\Nik`), NISN, Tanggal lahir · umur, Jenis kelamin, Login terakhir; tautan "Lihat dan lengkapi data saya →" ke `me.show`; catatan "Data siswa tidak diubah di sini. Data terkunci saat pendaftaran sesi sedang diperiksa." |
| Operator sekolah | Sekolah, Akses (deskripsi role bila ada), Akun dibuat, Login terakhir |
| Admin kecamatan | Wilayah ("Seluruh sekolah di Kec. Sungai Beduk"), Akun dibuat, Login terakhir |

- Baris dipilih menurut data akun, bukan nama role yang di-hardcode: ada `student` → baris siswa; `school_id` terisi → baris sekolah; selain itu → baris wilayah. Nilai kosong ditampilkan "—"; baris "Akses" disembunyikan bila deskripsi role kosong.
- Login terakhir memakai `lib/date.ts` (format `28 Okt 2026 · 07.42`).

**Kolom kanan** (maks 720 px), empat kartu berurutan:
1. **Informasi kontak:** Nama lengkap (wajib), Nomor HP (opsional, hint "Gunakan nomor yang diawali 08."), Email (terkunci, ikon gembok) dengan hint "Email dipakai untuk masuk dan menerima notifikasi." dan tombol teks **Ubah email**; tombol **Simpan** (hanya nama dan HP).
2. **Notifikasi:** saklar "Kirim notifikasi ke email" (bawaan aktif), teks "Lonceng di aplikasi selalu aktif." Banner peringatan (warning) tampil selalu di bawah saklar: "Jika dimatikan, Anda tidak menerima email tenggat perbaikan dan pengingat sesi. Pantau lonceng secara berkala agar tidak terlewat."
3. **Keamanan:** form ubah password (Password saat ini, Password baru dengan aturan dan indikator `NewPasswordFields`, Konfirmasi) dengan tombol **Perbarui password**; pemisah; baris "Keluar dari perangkat lain" dengan penjelasan satu kalimat dan tombol sekunder.

**Responsif**
- Dua kolom hanya di ≥ 1280 px. Di bawahnya satu kolom: kartu identitas menjadi header ringkas (avatar 48, nama, lencana, sekolah, login terakhir) dengan bagian "Data diri" yang dilipat (tampilkan/sembunyikan, `aria-expanded`); daftar label-nilai lengkap ada di dalamnya.
- Tombol Simpan penuh lebar di HP.

**Perilaku dan state**
- Simpan kontak: validasi klien seperti sekarang, galat server di bawah field, spinner di tombol, toast "Profil diperbarui." Fokus ke field pertama yang salah (`focusFirstInvalid`).
- Saklar email: menyimpan **langsung** saat diubah (tanpa tombol Simpan), toast "Preferensi notifikasi disimpan.", saklar kembali ke nilai semula bila gagal disimpan disertai pesan galat; `role="switch"` dengan nama aksesibel dan `aria-checked`.
- Ubah password: perilaku sekarang, tidak berubah.
- **Keluar dari perangkat lain:** dialog menyebut dampak ("Semua perangkat lain akan keluar dari akun ini. Perangkat ini tetap masuk.") dan meminta Password saat ini; aksi destruktif (merah); sukses = toast "Perangkat lain sudah dikeluarkan."; password salah = galat di field dialog.
- **Ubah email:** dialog dengan Email baru dan Password saat ini. Sukses menutup dialog dan menampilkan banner info di halaman: "Kami mengirim link konfirmasi ke {email baru}. Email akun baru berlaku setelah Anda membuka link itu. Berlaku {N} menit." Email tidak berubah sebelum link dibuka. Email baru yang sudah dipakai akun lain ditolak dengan "Email sudah terdaftar." (satu pesan yang sama dengan form pengguna).
- Sesudah link konfirmasi dibuka: arahkan ke Profil dengan toast "Email akun diperbarui." Link kedaluwarsa atau tidak valid: halaman jalan buntu bertulis (pola 5.3 panduan UI) dengan tautan kembali ke Profil, bukan 403 mentah.

**Aksesibilitas:** semua kontrol dapat dioperasikan dengan keyboard; dialog memindahkan dan mengembalikan fokus; status saklar dan hasil simpan diumumkan (`role="status"`); area sentuh ≥ 40 px; bagian "Data diri" yang dilipat memakai tombol sungguhan.

## 5. Data dan perilaku sisi server

- **Rute:** `profile.edit` dan `profile.update` tetap di `/profile` (bantalan untuk tautan lama). Rute baru mengikuti konvensi rute bahasa Inggris RESTful yang berlaku (mis. `profile.notifications.update`, `profile.sessions.destroy`, `profile.email.update`, `profile.email.confirm`). Nama persisnya diserahkan ke builder. Semua di grup `auth`; tidak ada permission khusus (setiap akun mengelola akunnya sendiri). Rute tulis diberi `throttle`.
- **Kolom baru di `users`:** `email_notifications` boolean, bawaan `true`. Itu satu-satunya perubahan skema yang dipastikan.
- **Gerbang email di `BaseNotification::via()`:** `['database']` ditambah `'mail'` hanya bila `email_notifications` bernilai true. Berlaku untuk semua `BaseNotification` ke semua role. Email reset password dan aktivasi bukan `BaseNotification` sehingga tidak terpengaruh.
- **Keluar dari perangkat lain:** validasi `current_password`; akhiri semua sesi milik user selain sesi saat ini. Sesi disimpan di database (`SESSION_DRIVER=database`, tabel `sessions` sudah ada). Mekanisme (hapus baris `sessions` atau `Auth::logoutOtherDevices`) diserahkan ke builder; syaratnya sesi saat ini bertahan dan hasilnya teruji.
- **Ganti email:** butuh `current_password`; email baru lolos aturan yang sama dengan form pengguna (huruf kecil, trim, unik, maks 255). Link konfirmasi dikirim ke **email baru** dan hanya berlaku sampai kedaluwarsa; ada pemberitahuan ke **email lama** bahwa email akun diganti (tanpa tautan aksi). Hanya pemilik sesi yang sedang masuk yang boleh mengonfirmasi. Unik dicek lagi saat konfirmasi. Penyimpanan permintaan (URL bertanda tangan yang membawa email baru atau kolom `pending_*`) diserahkan ke builder; pilih yang paling kecil yang tetap aman. Perubahan email tercatat di log aktivitas (`LogsActivity` pada `User` sudah ada; pastikan perubahan `email` ikut tercatat dan password tidak).
- **Prop halaman:** `profile` (name, email, phone, role, email_notifications) dan `identity` (kunci baris menurut §4 dengan nilai siap tampil; NIK sudah dimasker di server, tidak pernah dikirim utuh). Jangan memakai nama prop `auth`, `flash`, `menu`, `notifications`.
- **Data siswa:** baca lewat `$user->student()->first()` (relasi ter-cache antar-request di tes). Query harus tahan global scope `owner`.
- **Dihapus/dirapikan:** `AuthenticatedLayout.vue` sisa Breeze masih menaut `profile.edit`; hapus bila terbukti tidak terpakai (diverifikasi builder dengan pencarian pemakai).

## 6. Pembagian pengerjaan

Tiga irisan, masing-masing rencana implementasi sendiri, berurutan:
1. **A, halaman dan keamanan dasar:** prop `identity`, kartu identitas dan susunan responsif, "Data diri" lipat di HP, login terakhir, "Keluar dari perangkat lain".
2. **B, notifikasi email:** kolom `email_notifications`, gerbang di `BaseNotification`, kartu Notifikasi dan saklar.
3. **C, ganti email:** dialog, link konfirmasi, pemberitahuan ke email lama, halaman jalan buntu, log aktivitas.

Tes: perluas `tests/Feature/ProfileTest.php` (Pest) dan tambahkan Vitest untuk komponen baru. Panduan UI §5.15 diperbarui di irisan A mengikuti keputusan ini.

## 7. Keputusan saya (bukan keputusan user; mohon ditinjau)

- Dua kolom hanya ≥ 1280 px, selaras dengan breakpoint panduan UI; di bawahnya satu kolom dengan header ringkas.
- Kartu identitas melekat (sticky) di desktop.
- Baris identitas ditentukan oleh data akun, bukan nama role; "Akses" memakai deskripsi role.
- Saklar email menyimpan langsung, dengan banner peringatan yang selalu tampil.
- Password **tidak** disembunyikan di balik dialog pada HP seperti yang tergambar di mockup (tombol "Ubah password"); form yang sama dipakai di semua lebar supaya tidak ada dua implementasi. Hanya "Data diri" yang dilipat.
- Token ganti email berlaku 60 menit.
- Ganti email mensyaratkan login saat membuka link; bila belum masuk, diarahkan login lalu kembali.
- Pemberitahuan "email akun diganti" ke email lama **selalu dikirim**, walau `email_notifications` mati, karena itu pemberitahuan keamanan (dikirim langsung, bukan lewat gerbang `BaseNotification`).
- `email_verified_at` tidak disentuh saat konfirmasi (tidak ada kode yang memakainya).
- Teks banner, dialog, dan toast seperti tertulis di §4 (bahasa Indonesia, dapat disunting).

## 8. Diserahkan ke builder

Nama rute, mekanisme sesi lain, penyimpanan permintaan ganti email, bentuk komponen (satu atau beberapa), komponen saklar (komponen `Switch` shadcn-vue/reka-ui tanpa dependensi baru), struktur kelas layanan (ikuti pola service-repository bila ada logika; controller tipis boleh langsung bila hanya satu pemanggilan), isi persis pesan galat validasi.
