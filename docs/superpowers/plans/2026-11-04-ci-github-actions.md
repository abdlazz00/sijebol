# CI GitHub Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (eksekusi inline).

**Goal:** Pengujian dan analisis statis berjalan otomatis dan dapat menjadi syarat merge ke `staging`/`main` (F-09), dan Vitest tidak flaky di CI (F-30).

**Architecture:** Dua workflow GitHub Actions (CI dan audit mingguan); syarat merge lewat branch protection di pengaturan repositori.

**Tech Stack:** GitHub Actions, PHP 8.4, MySQL 8.0, Node 24.

**Spec:** `docs/superpowers/specs/2026-11-04-ci-github-actions-design.md`

## Global Constraints
- Versi runtime sama dengan produksi untuk PHP 8.4 dan MySQL 8.0; Node 24 (syarat engine Vitest 5/jsdom 30), bukan Node 20 milik Dockerfile.
- Tidak ada rahasia di workflow; `permissions: contents: read`.
- Audit tidak menjadi required check.

## Review Focus
- Pest berjalan dari `.env.example` + kunci baru (tanpa `.env` lokal).
- ESLint memakai `--quiet` agar peringatan format lama tidak memblokir.
- Vitest lulus dengan worker bawaan.

### Task 1: F-30
**Files:** Modify `vitest.config.ts`
- [ ] `testTimeout: 15_000`; `npx vitest run` penuh bawaan lulus 274/274.

### Task 2: Workflow
**Files:** Create `.github/workflows/ci.yml`, `.github/workflows/audit.yml`
- [ ] Validasi YAML; jalankan manual semua perintah job; Pest dengan `.env.testing` tiruan CI.

### Task 3: Dokumen
- [ ] `deployment-guide.md` bagian 22 (branch protection langkah demi langkah); berkas review F-09 dan F-30.
