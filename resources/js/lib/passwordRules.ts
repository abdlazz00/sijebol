const RULES = [
    { label: 'Minimal 8 karakter', test: (p: string) => p.length >= 8 },
    { label: 'Mengandung huruf', test: (p: string) => /[A-Za-z]/.test(p) },
    { label: 'Mengandung angka', test: (p: string) => /\d/.test(p) },
];

/** Panduan tampilan; validasi sebenarnya ada di server. */
export function passwordRules(password: string) {
    return RULES.map((r) => ({ label: r.label, met: r.test(password) }));
}

export function passwordScore(password: string): 0 | 1 | 2 | 3 {
    return passwordRules(password).filter((r) => r.met).length as 0 | 1 | 2 | 3;
}
