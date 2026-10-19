export type StudentRow = {
    id: number;
    name: string;
    nisn: string;
    nik: string;
    age: number;
    school: string | null;
    docs: { kk: boolean; akta_lahir: boolean };
    eligible: boolean;
    reasons: string[];
};

export type StudentDetail = StudentRow & {
    birth_date: string;
    gender: 'L' | 'P';
};

/** Data diri milik siswa yang sedang login (NIK tidak dimasker). */
export type SelfStudent = {
    id: number;
    nik: string;
    nisn: string;
    name: string;
    birth_date: string;
    gender: 'L' | 'P';
};

export type DocumentType = 'kk' | 'akta_lahir';

export type StudentDocumentRow = {
    type: DocumentType;
    name: string;
    size: number;
    url: string;
    preview_url?: string;
    mime?: string | null;
};

export type StudentRegistrationRef = {
    id: number;
    title: string;
    status: string;
};
