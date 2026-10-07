<?php

use App\Domains\Access\Exceptions\AccessRuleException;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\UserService;
use App\Domains\Schools\Models\School;
use App\Models\User;
use Database\Seeders\AccessSeeder;

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    $this->service = app(UserService::class);
    $this->super = User::factory()->create()->assignRole('super-admin');
    $this->admin = User::factory()->create()->assignRole('admin-kecamatan');
    $this->school = School::factory()->create();
    $this->operatorRole = Role::findByName('operator-sekolah');
});

function payload(array $o = []): array
{
    return array_merge(['name' => 'Budi', 'email' => 'budi@x.test', 'phone' => null], $o);
}

test('membuat user menghasilkan akun belum aktif, role, pembuat, dan token', function () {
    ['user' => $user, 'token' => $token] = $this->service->create(
        payload(['role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);

    expect($user->password)->toBeNull()
        ->and($user->activated_at)->toBeNull()
        ->and($user->created_by)->toBe($this->super->id)
        ->and($user->hasRole('operator-sekolah'))->toBeTrue()
        ->and($user->school_id)->toBe($this->school->id)
        ->and($token)->toHaveLength(64)
        ->and($user->fresh()->status->value)->toBe('menunggu');
});

test('email dinormalisasi: huruf kecil dan tanpa spasi', function () {
    ['user' => $user] = $this->service->create(
        payload(['email' => '  Budi@X.TEST ', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);

    expect($user->email)->toBe('budi@x.test');
});

test('aturan sekolah mengikuti is_school_scoped role', function () {
    expect(fn () => $this->service->create(payload(['role_id' => $this->operatorRole->id]), $this->super))
        ->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->create(
            payload(['role_id' => Role::findByName('admin-kecamatan')->id, 'school_id' => $this->school->id]), $this->super))
        ->toThrow(AccessRuleException::class);
});

test('tidak boleh memberi role di luar hak pemberi, dan super-admin hanya oleh super-admin', function () {
    expect(fn () => $this->service->create(payload(['role_id' => Role::findByName('super-admin')->id]), $this->admin))
        ->toThrow(AccessRuleException::class);

    ['user' => $u] = $this->service->create(payload(['role_id' => Role::findByName('super-admin')->id]), $this->super);
    expect($u->hasRole('super-admin'))->toBeTrue();
});

test('email tidak dapat diubah setelah akun aktif, tetapi dapat sebelum aktif', function () {
    $aktif = User::factory()->create(['email' => 'lama@x.test'])->assignRole('admin-kecamatan');
    $this->service->update($aktif, payload(['email' => 'baru@x.test', 'role_id' => Role::findByName('admin-kecamatan')->id]), $this->super);
    expect($aktif->fresh()->email)->toBe('lama@x.test');

    ['user' => $pending] = $this->service->create(
        payload(['email' => 'p@x.test', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);
    $this->service->update($pending, payload(['email' => 'p2@x.test', 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);
    expect($pending->fresh()->email)->toBe('p2@x.test');
});

test('tidak dapat mengubah role diri sendiri', function () {
    expect(fn () => $this->service->update($this->admin, payload([
        'email' => $this->admin->email, 'role_id' => $this->operatorRole->id, 'school_id' => $this->school->id,
    ]), $this->admin))->toThrow(AccessRuleException::class);
});

test('nonaktifkan dan aktifkan kembali, tidak boleh diri sendiri', function () {
    $target = User::factory()->create()->assignRole('operator-sekolah');

    $this->service->deactivate($target, $this->admin);

    expect($target->fresh()->is_active)->toBeFalse()
        ->and(fn () => $this->service->deactivate($this->admin, $this->admin))->toThrow(AccessRuleException::class);

    $this->service->activate($target);
    expect($target->fresh()->is_active)->toBeTrue();
});

test('super-admin aktif terakhir tidak dapat dinonaktifkan atau diturunkan', function () {
    $other = User::factory()->create()->assignRole('admin-kecamatan');

    expect(fn () => $this->service->deactivate($this->super, $other))->toThrow(AccessRuleException::class)
        ->and(fn () => $this->service->update($this->super, payload([
            'email' => $this->super->email, 'role_id' => Role::findByName('admin-kecamatan')->id,
        ]), $other))->toThrow(AccessRuleException::class);

    $second = User::factory()->create()->assignRole('super-admin');
    $this->service->deactivate($second, $this->super); // masih ada satu lagi → boleh
    expect($second->fresh()->is_active)->toBeFalse();
});

test('kirim ulang aktivasi hanya untuk akun belum aktif dan mencabut token lama', function () {
    ['user' => $user, 'token' => $old] = $this->service->create(
        payload(['role_id' => $this->operatorRole->id, 'school_id' => $this->school->id]), $this->super);

    $new = $this->service->resendActivation($user);
    expect($new)->not->toBe($old);

    $aktif = User::factory()->create();
    expect(fn () => $this->service->resendActivation($aktif))->toThrow(AccessRuleException::class);
});
