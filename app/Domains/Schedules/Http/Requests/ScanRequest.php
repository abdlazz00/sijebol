<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Izin dijaga middleware rute (can:rekaman.pindai).
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:64']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode tiket tidak terbaca.',
            'code.max' => 'Kode tiket tidak valid.',
        ];
    }
}
