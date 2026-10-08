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

export type DocumentType = 'kk' | 'akta_lahir';

export type StudentDocumentRow = {
    type: DocumentType;
    name: string;
    size: number;
    url: string;
};

export type ImportResult = {
    imported: number;
    failed: { row: number; reason: string }[];
};

export type StudentRegistrationRef = {
    id: number;
    title: string;
    status: string;
};
