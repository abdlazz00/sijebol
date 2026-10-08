<?php

namespace App\Domains\Schedules\Http\Requests;

use App\Domains\Schedules\Models\Schedule;
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
            'location' => ['required', 'string', 'max:150'],
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
            'location.required' => 'Lokasi wajib diisi.',
        ];
    }
}
