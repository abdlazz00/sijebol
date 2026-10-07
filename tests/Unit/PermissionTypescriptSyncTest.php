<?php

use App\Domains\Access\Enums\Permission;

test('konstanta TypeScript permission sama dengan enum PHP', function () {
    $ts = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/permissions.ts');
    preg_match_all("/'([a-z]+\.[a-z-]+)'/", $ts, $matches);

    $fromTs = $matches[1];
    $fromPhp = Permission::values();
    sort($fromTs);
    sort($fromPhp);

    expect($fromTs)->toBe($fromPhp);
});
