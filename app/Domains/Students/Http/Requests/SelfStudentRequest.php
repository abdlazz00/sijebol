<?php

namespace App\Domains\Students\Http\Requests;

use App\Domains\Students\Support\StudentRules;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Data diri milik akun siswa; sekolah dan pemilik ditentukan server, bukan input. */
class SelfStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStudent();
    }

    /** Akun non-siswa tidak diberi tahu bahwa rute ini ada. */
    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return StudentRules::fields($this->user()->student()->value('id'));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return StudentRules::messages();
    }
}
