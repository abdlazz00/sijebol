# CI dengan GitHub Actions (F-09, F-30)

Sumber: temuan F-09 dan F-30 di `docs/reviews/2026-11-02-full-code-review.md`.

## Masalah

Tidak ada CI. Pest, Vitest, PHPStan, `vue-tsc`, dan Pint hanya berjalan bila diingat. Server men-deploy dengan `git pull` dari `staging`/`main` (`scripts/deploy-*.sh`), sehingga cabang yang gagal tes bisa ter-deploy.

## Keputusan (bawaan yang dipilih saya, dapat diubah)

- **Platform:** GitHub Actions (repositori `abdlazz00/sijebol` ada di GitHub).
- **Syarat deploy:** CI memberi status pada setiap push dan pull request ke `main`/`staging`. Syarat "harus hijau sebelum masuk" ditegakkan oleh **branch protection** (required status checks `Backend` dan `Frontend`), yang diatur pemilik di pengaturan repositori; skrip deploy tidak diubah. Karena server hanya menarik `staging`/`main`, kode yang sampai ke server sudah melewati CI.
- **Audit dependensi:** terpisah dari CI, mingguan dan manual, tidak menjadi required check. Basis data advisori berubah setiap hari; CVE baru tidak boleh menggagalkan merge yang tidak berkaitan.

## Rancangan

`.github/workflows/ci.yml` (push dan PR ke `main`/`staging`, batalkan run lama pada ref yang sama, izin `contents: read`):

- **Backend** (ubuntu, PHP 8.4 seperti image produksi, MySQL 8.0 seperti server, root/root, database `sijebol_test`): `composer install`, `cp .env.example .env` + `key:generate`, `pint --test`, `phpstan analyse`, `pest`, `bash scripts/test-deploy-lib.sh`.
- **Frontend** (ubuntu, Node 20 seperti tahap build Dockerfile): `composer install --no-scripts` (Ziggy dibaca `vue-tsc` dan Vite), `npm ci`, ESLint `--quiet` (hanya galat; 332 peringatan format ditangani di F-21), `vitest run`, `npm run build` (vue-tsc + Vite).

`.github/workflows/audit.yml` (Senin 02:00 UTC dan manual): `composer audit` dan `npm audit --omit=dev`. Advisori alat build/dev dicatat di F-10, bukan di sini.

**F-30** ikut diselesaikan karena Vitest akan flaky di runner kecil: `vitest.config.ts` mendapat `testTimeout: 15_000`. Run penuh bawaan sebelumnya gagal 2 sampai 4 tes (timeout 5 detik) di mesin sibuk; sesudahnya 274/274 lulus.

## Di luar cakupan
- Prettier `--check` (41 berkas belum rapi, F-21) dan `--max-warnings 0` pada ESLint.
- Build image Docker di CI dan deploy otomatis (deploy tetap manual lewat SSH).
- Cache hasil `node_modules` di luar cache npm bawaan `setup-node`.

## Verifikasi
Workflow tidak dapat dijalankan sebelum di-push ke GitHub (belum dilakukan atas permintaan pemilik). Yang dibuktikan secara lokal: YAML valid; setiap perintah job dijalankan manual (Pint penuh, PHPStan, Pest penuh dalam lingkungan yang meniru CI dengan `.env.example` + kunci baru, ESLint, Vitest penuh, `npm run build`, `bash scripts/test-deploy-lib.sh`). Run pertama di GitHub tetap perlu diamati.
