# View Dokumen Siswa (Document Preview) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan fitur pratinjau dokumen siswa (PDF dan Gambar Kartu Keluarga & Akta Kelahiran) secara in-app modal yang lapang dan responsif, guna mempermudah verifikator saat memverifikasi berkas pendaftaran perekaman KTP-el serta operator saat mengelola data siswa.

**Architecture:** 
- Backend: Endpoint streaming privat `GET /siswa/{student}/dokumen/{type}/preview` dengan header `Content-Disposition: inline`, mime type dinamis, otorisasi `can:dokumen.lihat`, dan pencatatan audit log `dokumen.dilihat`.
- Frontend: Komponen reusable `DocumentPreviewDialog.vue` berbasis Reka UI `Dialog` dengan ukuran lapang (`max-w-5xl h-[85vh]`), tab switcher instan antara KK dan Akta, native `<iframe>` untuk PDF berfitur lengkap, image viewer responsif dengan kontrol zoom (In/Out/Reset), tombol "Buka di Tab Baru" & "Unduh", serta integrasi langsung di `Siswa/Show.vue` (via `FileUpload.vue`) dan `Verifikasi/Show.vue`.
- Prinsip Desain: **Ponytail** (zero new dependencies, native browser PDF rendering & CSS transform zoom) dan **Impeccable** (a11y keyboard/contrast, visual hierarchy terarah, layout lapang, loading & error fallbacks).

**Tech Stack:** Laravel 12, Inertia.js v2, Vue 3, TypeScript, Tailwind CSS v4, Lucide Vue, Pest PHP, Vitest.

**Spec:** Brainstorming session 2026-10-11 (Alur Verifikasi Perekaman KTP-el SIJEBOL).

## Global Constraints

- Bahasa UI wajib menggunakan Bahasa Indonesia baku resmi SIJEBOL.
- Dilarang menambahkan baris `Co-Authored-By` pada git commit.
- Tidak menambahkan dependency baru pada `composer.json` maupun `package.json` (memanfaatkan native platform browser & Tailwind CSS).
- Semua pengujian Pest (460+ tests) dan static analysis (PHPStan & Pint) wajib 100% lulus.

---

## Ponytail Review (Anti-Overengineering & Simplicity)

- **Native PDF Rendering:** Menggunakan `<iframe :src="preview_url">` alih-alih memasang library pihak ketiga yang berat (seperti `pdfjs-dist` atau paket wrapper lainnya). Browser modern sudah memiliki viewer PDF lengkap dengan akselerasi perangkat keras, pencarian kata (Ctrl+F), navigasi halaman, dan cetak.
- **Native Image Zoom:** Menggunakan reactive CSS `transform: scale(zoom)` dengan container `overflow-auto`, alih-alih memasang plugin pan-zoom eksternal.
- **Single Reusable Dialog:** Komponen `DocumentPreviewDialog.vue` digunakan bersama oleh halaman Detail Siswa dan Verifikasi, menghindari duplikasi logika pratinjau.
- **Standard Laravel Response:** Menggunakan `Storage::disk('local')->response(...)` standar Laravel untuk pengiriman berkas inline secara aman.

---

## Impeccable Review (Craft, UX, & Accessibility)

- **Modal Sizing & Viewport:** `max-w-5xl w-[95vw] h-[85vh]` memberi ruang baca maksimal untuk dokumen folio / lanskap (Kartu Keluarga) dan potret (Akta Kelahiran).
- **Tab Switcher Dokumen:** Tab segment bertombol jelas di header modal (`[Kartu Keluarga]` dan `[Akta Kelahiran]`) memungkinkan verifikator berpindah berkas seketika tanpa menutup modal.
- **Kontrol Viewer Gambar:** Floating/docked toolbar dengan tombol `ZoomIn`, `ZoomOut`, `Reset`, dan persentase zoom (`100%`, `125%`, dll.) memudahkan membaca NIK atau nomor seri kecil pada foto dokumen.
- **Aksi Cepat:** Tombol "Buka di Tab Baru" (`ExternalLink`) dan "Unduh" (`Download`) selalu tersedia di header untuk fleksibilitas kerja pengguna monitor ganda.
- **A11y & Kontras:** `DialogTitle` dan `DialogDescription` tersemat lengkap, navigasi keyboard (Esc untuk tutup, Tab untuk fokus), serta focus ring jelas.
- **Error & Loading Fallback:** Tampilan placeholder loading saat berkas dimuat, serta kartu kesalahan elegan jika berkas rusak dengan tombol alternatif langsung unduh berkas asli.

---

### Task 1: Backend Preview Endpoint & Service Method (TDD)

**Files:**
- Modify: `routes/students.php`
- Modify: `app/Domains/Students/Services/StudentDocumentService.php`
- Modify: `app/Domains/Students/Http/Controllers/StudentDocumentController.php`
- Test: `tests/Feature/Students/StudentDocumentTest.php`

**Interfaces:**
- Consumes: `Student`, `type` (`kk` | `akta_lahir`), `User $actor`
- Produces: `StreamedResponse` / `BinaryFileResponse` dengan `Content-Disposition: inline`, `preview_url` pada payload `rows()`

- [ ] **Step 1: Tulis tes unit/feature untuk endpoint preview dokumen (RED)**

Tambahkan tes di `tests/Feature/Students/StudentDocumentTest.php`:
```php
test('pratinjau dokumen menyajikan berkas inline, mencatat log, dan memeriksa otorisasi', function () {
    $this->actingAs($this->op);
    $this->post("/siswa/{$this->student->id}/dokumen/kk", ['file' => pdf('kk.pdf')]);

    $res = $this->get("/siswa/{$this->student->id}/dokumen/kk/preview")->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('inline')
        ->and($res->headers->get('content-type'))->toBe('application/pdf')
        ->and(Activity::where('description', 'dokumen.dilihat')->count())->toBe(1);

    // Pengguna tanpa izin dilarang
    $tanpaIzin = makeUserWith(['siswa.lihat'], $this->school, true, 'tanpa-dok-lihat');
    $this->actingAs($tanpaIzin)->get("/siswa/{$this->student->id}/dokumen/kk/preview")->assertForbidden();

    // Sekolah lain diblokir
    $sekolahLain = School::factory()->create();
    $this->actingAs(makeUserWith(['siswa.lihat', 'dokumen.lihat'], $sekolahLain, true, 'op-lain'))
        ->get("/siswa/{$this->student->id}/dokumen/kk/preview")->assertNotFound();
});
```

- [ ] **Step 2: Jalankan tes untuk memverifikasi kegagalan (RED)**

Run: `php artisan test --filter=StudentDocumentTest`
Expected: FAIL karena rute atau method `preview` belum ada.

- [ ] **Step 3: Implementasi endpoint dan method `preview()` (GREEN)**

1. Di `app/Domains/Students/Services/StudentDocumentService.php`:
```php
public function preview(Student $student, string $type, User $actor): StreamedResponse
{
    $document = $student->documents()->where('type', $type)->firstOrFail();
    abort_unless(Storage::disk(self::DISK)->exists($document->path), 404);

    activity('siswa')->performedOn($student)->causedBy($actor)->withProperties(['jenis' => $type])->log('dokumen.dilihat');

    return Storage::disk(self::DISK)->response(
        $document->path,
        $this->fileName($student, $document),
        [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$this->fileName($student, $document).'"',
        ],
    );
}
```
Perbarui method `rows()` untuk menyertakan `preview_url` dan `mime`:
```php
public function rows(Student $student): array
{
    return $student->documents->map(fn (StudentDocument $d) => [
        'type' => $d->type,
        'name' => $this->fileName($student, $d),
        'size' => $d->size,
        'mime' => $d->mime,
        'url' => route('siswa.dokumen.download', [$student, $d->type]),
        'preview_url' => route('siswa.dokumen.preview', [$student, $d->type]),
    ])->values()->all();
}
```

2. Di `app/Domains/Students/Http/Controllers/StudentDocumentController.php`:
```php
public function preview(Request $request, Student $student, string $type): StreamedResponse
{
    return $this->documents->preview($student, $type, $request->user());
}
```

3. Di `routes/students.php`:
```php
Route::prefix('siswa/{student}/dokumen/{type}')->whereIn('type', StudentRules::DOCUMENT_TYPES)->group(function () {
    Route::post('/', [StudentDocumentController::class, 'store'])->middleware(['can:dokumen.unggah', 'throttle:60,1'])->name('siswa.dokumen.store');
    Route::get('/', [StudentDocumentController::class, 'download'])->middleware('can:dokumen.lihat')->name('siswa.dokumen.download');
    Route::get('/preview', [StudentDocumentController::class, 'preview'])->middleware('can:dokumen.lihat')->name('siswa.dokumen.preview');
    Route::delete('/', [StudentDocumentController::class, 'destroy'])->middleware('can:dokumen.hapus')->name('siswa.dokumen.destroy');
});
```

- [ ] **Step 4: Jalankan tes untuk memverifikasi kelulusan (GREEN)**

Run: `php artisan test --filter=StudentDocumentTest`
Expected: PASS seluruh tes di `StudentDocumentTest`.

---

### Task 2: TypeScript Types & Komponen Reusable `DocumentPreviewDialog.vue`

**Files:**
- Modify: `resources/js/types/student.ts`
- Create: `resources/js/Components/shared/DocumentPreviewDialog.vue`
- Create: `resources/js/Components/shared/DocumentPreviewDialog.test.ts`

**Interfaces:**
- Props:
  - `open: boolean` (v-model:open)
  - `title: string` (nama siswa)
  - `documents: StudentDocumentRow[]`
  - `initialType?: DocumentType`
- Slots / Controls: Tab switcher dokumen, Zoom toolbar (gambar), Eksternal link, Download, Tutup.

- [ ] **Step 1: Perbarui tipe data `StudentDocumentRow`**

Di `resources/js/types/student.ts`:
```ts
export type StudentDocumentRow = {
    type: DocumentType;
    name: string;
    size: number;
    url: string;
    preview_url?: string;
    mime?: string;
};
```

- [ ] **Step 2: Buat unit test Vitest untuk `DocumentPreviewDialog.vue` (RED)**

Di `resources/js/Components/shared/DocumentPreviewDialog.test.ts`:
Verifikasi bahwa dialog menampilkan judul, tombol tab dokumen, berpindah dokumen saat tab diklik, dan merender iframe untuk PDF atau img untuk gambar.

- [ ] **Step 3: Implementasikan `DocumentPreviewDialog.vue` (GREEN)**

Buat komponen `resources/js/Components/shared/DocumentPreviewDialog.vue` dengan:
- Dialog Reka UI (`Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`).
- Styling lapang: `max-w-5xl w-[95vw] h-[85vh] p-0 flex flex-col overflow-hidden`.
- Header bar rapi dengan tab switcher dokumen (`Kartu Keluarga`, `Akta Kelahiran`), tombol aksi ("Buka di Tab Baru", "Unduh", "Tutup").
- Penampil berkas:
  - Jika PDF (`mime === 'application/pdf'` atau nama berakhiran `.pdf`): `<iframe :src="activeDoc.preview_url" class="w-full h-full border-0 bg-muted/20" />`.
  - Jika Gambar: Toolbar zoom (Zoom In, Zoom Out, Reset, persentase) + container scroll dengan transisi skala halus.
  - Fallback error jika berkas kosong / gagal.

- [ ] **Step 4: Jalankan test Vitest untuk memverifikasi (GREEN)**

Run: `npx vitest run resources/js/Components/shared/DocumentPreviewDialog.test.ts`
Expected: PASS.

---

### Task 3: Integrasi Pratinjau Dokumen pada Detail Siswa (`Siswa/Show.vue` & `FileUpload.vue`)

**Files:**
- Modify: `resources/js/Components/shared/FileUpload.vue`
- Modify: `resources/js/Pages/Siswa/Show.vue`

**Interfaces:**
- `FileUpload.vue` memancarkan event `preview` atau menyediakan tombol "Lihat" ketika dokumen tersedia (`existing !== null`).
- `Siswa/Show.vue` mengontrol dialog pratinjau `DocumentPreviewDialog.vue` dengan berkas KK dan Akta siswa.

- [ ] **Step 1: Tambahkan tombol "Lihat" pada `FileUpload.vue`**

Di `resources/js/Components/shared/FileUpload.vue`:
Tambahkan emit `preview`:
```ts
const emit = defineEmits<{
    (e: 'preview'): void;
}>();
```
Di samping tombol "Unduh", tambahkan tombol:
```html
<Button
    type="button"
    variant="ghost"
    class="h-10 px-3"
    @click="emit('preview')"
>
    <Eye class="size-4" aria-hidden="true" />
    Lihat
</Button>
```

- [ ] **Step 2: Pasang `DocumentPreviewDialog` pada `resources/js/Pages/Siswa/Show.vue`**

1. Tambahkan state `previewOpen = ref(false)` dan `selectedDocType = ref<DocumentType>('kk')`.
2. Ikat event `@preview="openPreview(doc.type)"` pada masing-masing `<FileUpload>`.
3. Render `<DocumentPreviewDialog v-model:open="previewOpen" :title="student.name" :documents="documents" :initial-type="selectedDocType" />`.

---

### Task 4: Integrasi Pratinjau Dokumen pada Verifikasi Perekaman (`Verifikasi/Show.vue`)

**Files:**
- Modify: `resources/js/Pages/Verifikasi/Show.vue`

**Interfaces:**
- Verifikator dapat mengklik tombol "Lihat" di samping tombol "Unduh" pada setiap item daftar dokumen.
- `DocumentPreviewDialog` terbuka dengan dokumen terkait langsung aktif, serta verifikator dapat beralih antara KK dan Akta melalui tab switcher tanpa meninggalkan halaman verifikasi.

- [ ] **Step 1: Tambahkan tombol "Lihat" dan integrasikan `DocumentPreviewDialog` pada `Verifikasi/Show.vue`**

Di `resources/js/Pages/Verifikasi/Show.vue`:
1. Import `DocumentPreviewDialog` dan ikon `Eye`.
2. Tambahkan ref `previewOpen = ref(false)` dan `selectedDocType = ref<DocumentType>('kk')`.
3. Pada list item dokumen:
```html
<div class="flex items-center gap-2">
    <Button
        type="button"
        variant="default"
        size="sm"
        class="h-9 px-3 gap-1.5"
        @click="openPreview(doc.type)"
    >
        <Eye class="size-4" aria-hidden="true" />
        Lihat
    </Button>
    <Button as-child variant="outline" size="sm" class="h-9 px-3">
        <a :href="doc.url">
            <Download class="size-4" aria-hidden="true" />
            Unduh
            <span class="sr-only">{{ doc.name }}</span>
        </a>
    </Button>
</div>
```
4. Render `<DocumentPreviewDialog v-model:open="previewOpen" :title="`Dokumen ${student.name}`" :documents="documents" :initial-type="selectedDocType" />`.

---

### Task 5: Verifikasi Menyeluruh & Quality Gates

**Files:**
- Audit & Test All

- [ ] **Step 1: Jalankan seluruh test suite backend Pest**
Run: `php artisan test --parallel`
Expected: 461+ tests PASS (0 failures).

- [ ] **Step 2: Jalankan PHPStan / Larastan**
Run: `vendor/bin/phpstan analyse`
Expected: 0 errors.

- [ ] **Step 3: Jalankan Laravel Pint**
Run: `vendor/bin/pint --test`
Expected: Format kode rapi sesuai standar PSR-12.

- [ ] **Step 4: Jalankan Frontend Build & Test**
Run: `npm run build` dan `npm run test`
Expected: Build TypeScript & Vite sukses tanpa error.
