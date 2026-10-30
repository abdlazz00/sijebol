<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Nik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->currentRole()?->name,
            ],
            'identity' => $this->identity($user),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return Redirect::route('profile.edit')->with('success', 'Profil diperbarui.');
    }

    /**
     * Data mentah untuk kartu identitas; baris tampil disusun di klien. NIK sudah dimasker.
     *
     * @return array<string, mixed>
     */
    private function identity(User $user): array
    {
        $student = $user->student()->first();

        return [
            'school' => $user->school?->name,
            'student' => $student ? [
                'nik' => Nik::mask($student->nik),
                'nisn' => $student->nisn,
                'birth_date' => $student->birth_date->toDateString(),
                'age' => $student->birth_date->age,
                'gender' => $student->gender,
            ] : null,
            'access' => $user->currentRole()?->description,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'can_open_student_data' => $user->isStudent() && $user->can('profil.lihat'),
        ];
    }
}
