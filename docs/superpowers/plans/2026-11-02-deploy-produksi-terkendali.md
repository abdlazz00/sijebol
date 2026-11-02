# Deploy produksi terkendali — rencana

**Spec:** `docs/superpowers/specs/2026-11-02-deploy-produksi-terkendali-design.md`. Eksekusi inline.

- [x] `scripts/test-deploy-lib.sh`: uji `env_value`, `backup_database` (sukses, dump gagal, dump kosong, tanpa sisa `.partial`, izin bila didukung), `prune_backups` (batas 14 hari, cadangan database lain dan berkas lain tidak disentuh). Gagal sebelum pustaka ada.
- [x] `scripts/lib/deploy.sh`: tiga fungsi di atas. Seluruh uji lulus.
- [x] `scripts/deploy-prod.sh` ditulis ulang dengan alur di spec; `PROJECT_DIR` dan `BACKUP_DIR` dapat di-override env untuk simulasi.
- [x] `docker-compose.prod.yml`: `AUTO_MIGRATE=false` (diverifikasi lewat `docker compose config`).
- [x] Simulasi dengan `docker` dan `git` tiruan, empat skenario: sukses, cadangan gagal, migrasi gagal, konfirmasi ditolak. Urutan perintah sesuai spec.
- [x] Dokumentasi: `deployment-guide.md` bagian 17.
