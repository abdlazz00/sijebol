# Hapus Sekolah Mempertahankan Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (eksekusi native, inline) untuk mengerjakan rencana ini. Langkah memakai checkbox (`- [ ]`).

**Goal:** Setelah sekolah dihapus, daftar `/schools` tetap memakai filter pencarian/jenjang dan halaman yang sedang dibuka.

**Architecture:** `SchoolController::destroy` mengembalikan `back()` (redirect ke Referer) alih-alih `redirect()->route('schools.index')`, sama seperti `UserController` dan `SchoolLinkController`.

**Tech Stack:** Laravel, Pest.

**Spec:** Perubahan kecil, disepakati di chat (temuan pengecekan manual hapus sekolah 2026-10-31).

## Global Constraints

- Pesan flash sukses tetap `Sekolah dihapus.`.
- Tanpa komentar/abstraksi baru; ikuti pola `back()->with('success', ...)` yang sudah ada.

## Review Focus

- Hapus dari halaman ber-filter: redirect kembali ke URL ber-filter itu (diuji di Task 1).
- Hapus item terakhir di halaman terakhir menghasilkan halaman kosong: diterima, bukan cakupan perbaikan ini.

---

### Task 1: destroy kembali ke halaman asal

**Files:**
- Modify: `app/Domains/Schools/Http/Controllers/SchoolController.php` (`destroy`)
- Test: `tests/Feature/Schools/SchoolDeleteTest.php` (tes `sekolah tanpa data terkait dapat dihapus oleh pengguna berizin`)

**Interfaces:**
- Consumes: `SchoolService::delete(School, User): void` (tidak berubah).
- Produces: `destroy(Request, School): RedirectResponse` yang me-redirect ke Referer.

- [ ] **Step 1: Ubah tes** agar request memakai `->from('/schools?filter[search]=QA')` sebelum `->delete(...)`, dan `->assertRedirect('/schools?filter[search]=QA')` menggantikan `->assertRedirect('/schools')`; `assertSessionHas('success', 'Sekolah dihapus.')` dipertahankan.
- [ ] **Step 2: Jalankan** `php artisan test --compact tests/Feature/Schools/SchoolDeleteTest.php`. Harapan: tes tersebut GAGAL (redirect ke `/schools`).
- [ ] **Step 3: Ubah `destroy`** menjadi `return back()->with('success', 'Sekolah dihapus.');`.
- [ ] **Step 4: Jalankan ulang** perintah Step 2. Harapan: semua tes di berkas lulus.
