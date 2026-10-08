<?php

use App\Domains\Audit\Services\ActivityPropertyMasker;

test('membuang kunci rahasia dan memasker NIK', function () {
    $hasil = ActivityPropertyMasker::apply([
        'name' => 'Budi', 'password' => 'x', 'password_confirmation' => 'x',
        'token' => 't', 'remember_token' => 'r', 'nik' => '3201123412341234',
    ]);

    expect($hasil)->toBe(['name' => 'Budi', 'nik' => '3201********1234']);
});

test('NIK pendek dimasker penuh dan nilai bukan string dibiarkan', function () {
    expect(ActivityPropertyMasker::apply(['nik' => '12345']))->toBe(['nik' => '*****'])
        ->and(ActivityPropertyMasker::apply(['nik' => null]))->toBe(['nik' => null]);
});
