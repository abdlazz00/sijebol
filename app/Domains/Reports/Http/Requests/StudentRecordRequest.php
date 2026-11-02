<?php

namespace App\Domains\Reports\Http\Requests;

use App\Domains\Reports\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRecordRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.school_id' => ['nullable', 'integer'],
            'filter.schedule_id' => ['nullable', 'integer'],
            'filter.search' => ['nullable', 'string', 'max:100'],
            'filter.group' => ['nullable', Rule::in(array_keys(ReportService::GROUPS))],
            'filter.dari' => ['nullable', 'date_format:Y-m-d'],
            // after_or_equal hanya bila `dari` terisi (pola ReportRequest).
            'filter.sampai' => ['nullable', 'date_format:Y-m-d', ...($this->filled('filter.dari') ? ['after_or_equal:filter.dari'] : [])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'filter.group.in' => 'Kelompok tidak dikenal.',
            'filter.dari.date_format' => 'Format tanggal tidak valid.',
            'filter.sampai.date_format' => 'Format tanggal tidak valid.',
            'filter.sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ];
    }

    /**
     * Filter yang sudah divalidasi, dalam bentuk yang dipakai ReportService::studentRecords.
     *
     * @return array{school_id?: int, schedule_id?: int, search?: string, group?: string, dari?: string, sampai?: string}
     */
    public function filters(): array
    {
        $filter = array_filter($this->validated('filter') ?? [], fn ($v) => $v !== null && $v !== '');

        foreach (['school_id', 'schedule_id'] as $key) {
            if (isset($filter[$key])) {
                $filter[$key] = (int) $filter[$key];
            }
        }

        return $filter;
    }
}
