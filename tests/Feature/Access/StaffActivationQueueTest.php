<?php

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\UserService;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Facades\DB;

test('token aktivasi staf tidak tersimpan sebagai teks biasa di tabel antrean', function () {
    $this->seed(AccessSeeder::class);
    config(['queue.default' => 'database']);
    $super = User::factory()->create()->assignRole('super-admin');

    ['token' => $token] = app(UserService::class)->create([
        'name' => 'Budi', 'email' => 'budi@x.test', 'phone' => null,
        'role_id' => Role::findByName('operator-sekolah')->id, 'school_id' => School::factory()->create()->id,
    ], $super);

    $jobs = DB::table('jobs')->pluck('payload');
    expect($jobs)->toHaveCount(1)
        ->and($jobs->first())->not->toContain($token);
});
