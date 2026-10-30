/** Data mentah kartu identitas halaman Profil; baris tampil disusun di komponen. NIK sudah dimasker server. */
export type ProfileIdentity = {
    school: string | null;
    student: {
        nik: string;
        nisn: string;
        birth_date: string;
        age: number;
        gender: 'L' | 'P';
    } | null;
    access: string | null;
    created_at: string;
    last_login_at: string | null;
    can_open_student_data: boolean;
};
