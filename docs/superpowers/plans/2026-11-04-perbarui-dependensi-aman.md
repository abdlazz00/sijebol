# Perbarui Dependensi Aman Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** Menutup advisori dependensi yang dapat ditutup tanpa pembaruan mayor (F-10).

**Architecture:** Hanya `composer.lock`, `package.json`, `package-lock.json`; tanpa perubahan kode aplikasi.

**Tech Stack:** Composer, npm.

**Spec:** `docs/superpowers/specs/2026-11-04-perbarui-dependensi-aman-design.md`

## Global Constraints
- Jangan memakai `npm audit fix --force`.
- Perbarui hanya `symfony/mime` di Composer.
- Semua tes tetap lulus.

## Review Focus
- Build produksi (`npm run build`) tetap berhasil dengan `shadcn-vue` di `devDependencies`.
- Suite Pest dan Vitest tidak berubah hasilnya.

### Task 1: Pembaruan
- [ ] `composer update symfony/mime`; `composer audit` bersih.
- [ ] `npm audit fix`; `npm install --save-dev shadcn-vue@^2.9.0`; `npm audit --omit=dev` 0.

### Task 2: Verifikasi dan dokumen
- [ ] `npm run build`, `vue-tsc`, ESLint (0 galat), Pest penuh, Vitest penuh.
- [ ] Berkas review: status F-10, sisa advisori, dan temuan baru F-30 (Vitest timeout di bawah beban).
