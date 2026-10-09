<?php

namespace App\Domains\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            // after_or_equal hanya bila `dari` terisi; tanpa itu Laravel membandingkan dengan teks "dari".
            'sampai' => ['nullable', 'date_format:Y-m-d', ...($this->filled('dari') ? ['after_or_equal:dari'] : [])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dari.date_format' => 'Format tanggal tidak valid.',
            'sampai.date_format' => 'Format tanggal tidak valid.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ];
    }
}
