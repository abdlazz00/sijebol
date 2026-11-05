<?php

return [
    // CSP diberlakukan di luar lingkungan local; matikan lewat SECURITY_CSP=false hanya untuk keadaan darurat.
    'csp' => (bool) env('SECURITY_CSP', true),

    // Batas permintaan web per menit per pengguna (tamu: per IP).
    'rate_limit' => (int) env('SECURITY_RATE_LIMIT', 240),

    // Login gagal per IP (semua email digabung) dalam jendela detik tertentu, lalu IP itu dikunci sampai jendela habis.
    // Hanya kegagalan yang dihitung agar satu kelas di Wi-Fi sekolah yang login bersamaan tidak ikut terkunci.
    'login_ip_failures' => (int) env('SECURITY_LOGIN_IP_FAILURES', 50),
    'login_ip_window' => (int) env('SECURITY_LOGIN_IP_WINDOW', 600),
];
