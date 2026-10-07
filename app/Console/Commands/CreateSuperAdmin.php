<?php

namespace App\Console\Commands;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\ActivationService;
use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'sijebol:create-super-admin {email} {name?}';

    protected $description = 'Buat super-admin pertama dan cetak link aktivasinya';

    public function handle(ActivationService $activations): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $role = Role::where('name', Role::SUPER_ADMIN)->first();

        if ($role === null) {
            $this->error('Role super-admin belum ada. Jalankan: php artisan db:seed --class=AccessSeeder');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("Email {$email} sudah terdaftar.");

            return self::FAILURE;
        }

        $user = User::create(['name' => $this->argument('name') ?? 'Super Admin', 'email' => $email]);
        $user->assignRole($role);

        $this->info('Super-admin dibuat. Kirim link ini (berlaku 3 hari):');
        $this->line(route('aktivasi.show', $activations->issue($user)));

        return self::SUCCESS;
    }
}
