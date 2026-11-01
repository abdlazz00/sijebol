<?php

namespace App\Domains\Schedules\Http\Requests;

use App\Domains\Schedules\Models\Schedule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Satu kelas untuk buat dan ubah; `$this->route('schedule')` kosong saat buat. */
class ScheduleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schedule = $this->route('schedule');
        // Tanggal lampau yang tidak diubah boleh tetap (mis. hanya mengganti lokasi); tanggal baru tidak boleh di masa lalu.
        $dateChanged = ! $schedule instanceof Schedule || $this->input('date') !== $schedule->date->toDateString();

        return [
            // Operator sekolah dikunci ke sekolahnya; hanya staf memilih sekolah, dan hanya saat buat.
            'school_id' => $this->user()->school_id === null && $this->isMethod('post')
                ? ['required', 'integer', 'exists:schools,id']
                : ['nullable'],
            'date' => ['required', 'date_format:Y-m-d', ...($dateChanged ? ['after_or_equal:'.Schedule::today()] : [])],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'registration_closes_at' => [
                'bail', 'required', 'date_format:Y-m-d\TH:i',
                // Format tetap, jadi perbandingan teks sama dengan perbandingan waktu.
                fn (string $attribute, mixed $value, Closure $fail) => $value <= $this->input('date').'T'.$this->input('start_time')
                    ? null
                    : $fail('Batas pendaftaran tidak boleh melewati jam mulai sesi.'),
            ],
            'location' => ['required', 'string', 'max:150'],
            'quota' => ['required', 'integer', 'between:1,1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'school_id.required' => 'Pilih sekolah.',
            'date.required' => 'Tanggal wajib diisi.',
            'date.date_format' => 'Format tanggal tidak valid.',
            'date.after_or_equal' => 'Tanggal tidak boleh di masa lalu.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Format jam mulai tidak valid.',
            'end_time.required' => 'Jam selesai wajib diisi.',
            'end_time.date_format' => 'Format jam selesai tidak valid.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'registration_closes_at.required' => 'Batas pendaftaran wajib diisi.',
            'registration_closes_at.date_format' => 'Format batas pendaftaran tidak valid.',
            'location.required' => 'Lokasi wajib diisi.',
            'quota.required' => 'Kuota wajib diisi.',
            'quota.integer' => 'Kuota harus berupa angka 1–1000.',
            'quota.between' => 'Kuota harus berupa angka 1–1000.',
        ];
    }
}
