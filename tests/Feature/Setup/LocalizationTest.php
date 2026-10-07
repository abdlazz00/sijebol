<?php

test('aplikasi memakai locale dan zona waktu Indonesia', function () {
    expect(config('app.locale'))->toBe('id')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta');
});

test('pesan validasi berbahasa Indonesia', function () {
    $validator = validator(['email' => ''], ['email' => 'required']);

    $validator->fails();

    expect($validator->errors()->first('email'))->toContain('wajib diisi');
});
