# Perbarui dependensi yang rentan, bagian yang aman (F-10)

Sumber: temuan F-10 di `docs/reviews/2026-11-02-full-code-review.md`.

## Keadaan saat dikerjakan (4 Nov 2026)

- `composer audit`: `symfony/mime` v8.1.7 terdampak CVE-2026-45070 (header injection pada nama parameter MIME; diperbaiki di >= 8.1.10).
- `npm audit`: 47 advisori (2 kritis, 9 tinggi, 35 sedang, 1 rendah). Angka review awal (26) sudah naik karena basis data advisori bertambah.
- Alur pengiriman (Dockerfile): npm hanya dijalankan di tahap `frontend` (`npm ci` lalu `npm run build`); tahap produksi hanya menyalin `public/build`. Paket npm tidak ikut ke image produksi. Dampak advisori npm terbatas pada kontainer build dan mesin pengembang, bukan aplikasi yang melayani pengguna.
- Saran `npm audit fix --force` mengusulkan versi mayor yang salah arah (mis. `eslint@4`, `vite@6.4.5`, `shadcn-vue@0.11.4` sebagai penurunan versi), sehingga tidak diikuti.

## Rancangan (hanya bagian yang aman)

1. `composer update symfony/mime` (hanya paket itu): v8.1.7 menjadi v8.1.10.
2. `npm audit fix` tanpa `--force`: pembaruan semver-kompatibel di lockfile (menghilangkan dua advisori kritis `shell-quote`/`concurrently` dan sejumlah advisori tinggi seperti `undici`, `body-parser`, `follow-redirects`); ikut menaikkan `vite` 8.3.5 dan `shadcn-vue` 2.9.0 yang masih dalam rentang `package.json`.
3. Pindahkan `shadcn-vue` dari `dependencies` ke `devDependencies`. Ia dipakai saat build (`resources/css/app.css` meng-import `shadcn-vue/tailwind.css`); tahap build Docker memakai `npm ci` penuh sehingga tetap tersedia. Dengan ini `npm audit --omit=dev` memberi gambaran yang bermakna.

## Tidak dikerjakan (dicatat)

Sisa 38 advisori (6 tinggi, 31 sedang, 1 rendah) seluruhnya rantai alat build/dev: `stylus`/`css`/`decode-uri-component` (peer opsional `vite` dan `vue-metamorph` milik CLI `shadcn-vue`), `zod`/`deepmerge`/`ts-morph` (CLI `shadcn-vue`), dan `eslint` 8 beserta plugin (`@typescript-eslint`, `@vue/eslint-config-*`), `vue-tsc` 2, `js-beautify` (`@vue/test-utils`). Perbaikannya membutuhkan pembaruan mayor toolchain (ESLint 9/10 dengan flat config, vue-tsc 3, dan sebagainya) atau perbaikan di hulu; dijadwalkan sebagai pekerjaan terpisah. Audit otomatis di CI menunggu F-09.

## Pengujian

Tidak ada kode aplikasi yang berubah, jadi tidak ada tes baru. Verifikasi: `composer audit` bersih, `npm audit --omit=dev` 0 kerentanan, `npm run build` (vue-tsc + vite), ESLint 0 galat, Pest penuh, Vitest penuh.
