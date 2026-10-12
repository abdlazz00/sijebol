# Rencana Implementasi: Batas Umur Minimal Siswa (Tanpa Batas Atas)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghapus batas atas umur 17 tahun pada kelayakan (*eligibility*) siswa sehingga semua siswa berusia minimal 16 tahun (16, 17, 18, 19, dst.) berstatus memenuhi syarat untuk perekaman KTP-el.

**Architecture:** Memperbarui `EligibilityService` agar hanya memvalidasi `$age >= 16` dengan pesan ketidaklayakan `"Umur minimal 16 tahun (umur sekarang {age})."`. Mengganti filter batas rentang tanggal lahir pada `EloquentStudentRepository` menjadi `birth_date <= latestBirthDate`. Menyelaraskan unit dan feature test tanpa mengubah kontrak API dan label antarmuka frontend.

**Tech Stack:** Laravel 12, PHP 8.4+, Pest PHP, Inertia.js / Vue 3.

## Global Constraints

- Tidak ada breaking change pada kontrak API (`eligible: boolean`, `reasons: list<string>`).
- Label badge frontend tetap "Memenuhi syarat" dan "Belum memenuhi syarat" (tidak diubah).
- Dilarang menyertakan `Co-Authored-By` pada git commit (`CLAUDE.md`).
- Menjalankan seluruh quality gates (Pest, Pint, PHPStan, Vitest, npm build).

---

### Task 1: Pembaruan Domain Logic & Query Kelayakan Siswa

**Files:**
- Modify: `app/Domains/Students/Services/EligibilityService.php`
- Modify: `app/Domains/Students/Repositories/EloquentStudentRepository.php`
- Modify: `tests/Feature/Students/EligibilityServiceTest.php`
- Modify: `tests/Feature/Registrations/VerificationServiceTest.php`

**Interfaces:**
- Consumes: `Student->birth_date`, `CarbonInterface`
- Produces: `EligibilityService::check(): array{eligible: bool, reasons: list<string>}`, `EligibilityService::latestBirthDate(?CarbonInterface $on = null): string`

- [x] **Step 1: Tulis tes yang gagal (Failing Test) di `EligibilityServiceTest.php` dan `VerificationServiceTest.php`**
  - Di `tests/Feature/Students/EligibilityServiceTest.php`:
    - Ubah dataset umur agar 18 dan 19 tahun bernilai `eligible: true`.
    - Ubah ekspektasi alasan menjadi `'Umur minimal 16 tahun (umur sekarang 11).'`.
    - Uji fungsi batas tanggal lahir `latestBirthDate`.
  - Di `tests/Feature/Registrations/VerificationServiceTest.php`:
    - Ubah kasus uji umur tidak layak dari usia 25 tahun menjadi usia 15 tahun (`'umur di bawah 16'`).

- [x] **Step 2: Jalankan tes untuk memastikan kegagalan (Red Phase)**
  - Jalankan: `php artisan test tests/Feature/Students/EligibilityServiceTest.php`

- [x] **Step 3: Implementasikan kode minimal di `EligibilityService.php` dan `EloquentStudentRepository.php`**
  - Hapus `MAX_AGE = 17` dari `EligibilityService`.
  - Ubah kondisi kelayakan umur menjadi:
    ```php
    if ($age < self::MIN_AGE) {
        $reasons[] = "Umur minimal 16 tahun (umur sekarang {$age}).";
    }
    ```
  - Tambahkan metode `latestBirthDate(?CarbonInterface $on = null): string` dan pertahankan `birthDateBounds` (sebagai wrapper backwards-compatible jika diperlukan):
    ```php
    public function latestBirthDate(?CarbonInterface $on = null): string
    {
        return $this->day($on)->startOfDay()->subYears(self::MIN_AGE)->toDateString();
    }
    ```
  - Di `EloquentStudentRepository.php`:
    Ganti `$q->whereBetween('birth_date', ...)` menjadi:
    ```php
    $q->where('birth_date', '<=', $this->eligibility->latestBirthDate())
    ```

- [x] **Step 4: Jalankan tes spesifik untuk memastikan kelulusan (Green Phase)**
  - Jalankan: `php artisan test tests/Feature/Students/EligibilityServiceTest.php tests/Feature/Registrations/VerificationServiceTest.php`

- [x] **Step 5: Verifikasi seluruh suite pengujian dan quality gates**
  - Jalankan: `php artisan test --parallel`
  - Jalankan: `vendor/bin/pint --test`
  - Jalankan: `vendor/bin/phpstan analyse`
  - Jalankan: `npx vitest run`

---

## Ponytail Review

- **Does this need to exist at all?** Ya, pembatasan umur 17 tahun sebelumnya adalah bug logika domain karena siswa usia 18+ di sekolah menjadi tidak bisa direkam.
- **Can it be simpler?** Sangat sederhana. Hanya menghapus pengecekan batas atas (`|| $age > self::MAX_AGE`) dan menyederhanakan query database dari `whereBetween` menjadi satu perbandingan tanggal `where('birth_date', '<=', ...)`.
- **Zero new dependencies / abstractions:** Tidak menambah class, trait, config, atau migrasi database baru. Menggunakan fungsi Carbon dan Eloquent yang sudah ada.
- **Shortest diff:** Perubahan hanya terisolasi pada 2 file produksi (`EligibilityService.php` & `EloquentStudentRepository.php`) dan 2 file tes.
