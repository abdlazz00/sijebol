# AccessSeeder aditif — rencana

**Spec:** `docs/superpowers/specs/2026-11-02-seeder-aditif-design.md`. Eksekusi inline.

### Task 1: Tes lalu implementasi
- [x] Tambah tujuh tes di `tests/Feature/Access/AccessSeederTest.php` (describe "dijalankan ulang di server yang role-nya sudah diubah"): kustomisasi izin tidak dikembalikan; deskripsi tidak ditimpa; izin baru hanya ke role yang berhak; izin yang sengaja dicabut tidak kembali; super-admin selalu penuh; izin kedaluwarsa dibersihkan; role terhapus dibuat ulang. Empat gagal sebelum implementasi.
- [x] Ubah `database/seeders/AccessSeeder.php`: `wasRecentlyCreated` untuk izin, `firstOrCreate` untuk role, `givePermissionTo(bawaan ∩ baru)` untuk role yang ada, `syncPermissions` hanya untuk role baru dan super-admin, hapus izin yang tidak dikenal.
- [x] Verifikasi di DB lokal: isi role identik sebelum dan sesudah run (hash sama); tiga izin lama yang tidak dipakai dibersihkan.
- [x] Dokumentasi: `deployment-guide.md` bagian 16 dan catatan di bagian 9.
