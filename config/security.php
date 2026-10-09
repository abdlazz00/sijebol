<?php

return [
    // CSP diberlakukan di luar lingkungan local; matikan lewat SECURITY_CSP=false hanya untuk keadaan darurat.
    'csp' => (bool) env('SECURITY_CSP', true),

    // Batas permintaan web per menit per pengguna (tamu: per IP).
    'rate_limit' => (int) env('SECURITY_RATE_LIMIT', 240),
];
