<?php

namespace App\Domains\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['target_schedule_id' => ['required', 'integer', 'exists:schedules,id']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['target_schedule_id.required' => 'Pilih jadwal tujuan.', 'target_schedule_id.exists' => 'Jadwal tujuan tidak ditemukan.'];
    }
}
