# AccessSeeder aditif — desain

Asal: temuan F-01 di `docs/reviews/2026-11-02-full-code-review.md`.

## Masalah
`AccessSeeder` memanggil `syncPermissions` dan `updateOrCreate` pada role bawaan di setiap run, sehingga perubahan izin, deskripsi, dan pengaturan terikat sekolah yang dibuat lewat menu Role hilang tanpa peringatan. Seeder ini harus dijalankan di UAT/produksi setiap ada izin baru.

## Keputusan (pengguna: "Seeder aditif")
- Role dibuat bila belum ada; role yang ada tidak ditimpa.
- Izin yang baru dibuat pada run itu diberikan ke role bawaan yang memang mendapatkannya; izin yang sudah ada tidak diberikan lagi.
- super-admin selalu disinkron penuh.
- Kondisi server tidak diketahui (belum ada pengecekan kustomisasi); desain tidak mengasumsikan ada atau tidaknya.

## Keputusan builder
- Izin yang sudah tidak ada di `Permission::values()` dihapus. Alasan: rilis lama memakai `syncPermissions` untuk mencabut izin (mis. `pendaftaran.ajukan`); tanpa pembersihan izin lama tertinggal. Aman karena UI hanya menerima izin yang dikenal.
- Role bawaan yang terhapus dibuat kembali dengan izin bawaan.

## Bukan tujuan
Tidak memeriksa atau memigrasi kustomisasi yang sudah ada. Tidak menambah mekanisme migrasi izin; pencabutan izin bawaan di masa depan dilakukan lewat UI atau migrasi data.
