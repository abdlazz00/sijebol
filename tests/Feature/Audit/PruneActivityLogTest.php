<?php

use Spatie\Activitylog\Models\Activity;

test('menghapus hanya log yang lebih tua dari batas hari', function () {
    $lama = activity('akses')->log('lama');
    $lama->forceFill(['created_at' => now()->subDays(400)])->save();
    $batas = activity('akses')->log('hampir');
    $batas->forceFill(['created_at' => now()->subDays(364)])->save();
    activity('akses')->log('baru');

    $this->artisan('logs:prune')->expectsOutput('1 baris log dihapus.')->assertExitCode(0);

    expect(Activity::count())->toBe(2)
        ->and(Activity::where('description', 'lama')->exists())->toBeFalse();
});

test('opsi --days mengubah batas', function () {
    $log = activity('akses')->log('x');
    $log->forceFill(['created_at' => now()->subDays(40)])->save();

    $this->artisan('logs:prune', ['--days' => 30])->expectsOutput('1 baris log dihapus.');
    expect(Activity::count())->toBe(0);
});
