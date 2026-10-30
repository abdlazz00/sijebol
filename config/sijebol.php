<?php

return [
    // Pendaftaran sesi ditutup sekian hari sebelum tanggal sesi (hari terakhir mendaftar = tanggal sesi dikurangi nilai ini, inklusif).
    'registration_close_days' => 2,

    // Batas perbaikan: sekian jam sejak diminta, tetapi tidak melewati akhir hari sebelum sesi.
    'fix_window_hours' => 48,

    // Pengingat sesi H-1 dikirim sejak jam ini (WIB).
    'reminder_hour' => 8,

    // Pemindaian tiket dibuka sekian menit sebelum jam mulai dan ditutup sekian menit setelah jam selesai (pada tanggal sesi).
    'scan_tolerance_minutes' => 60,
];
