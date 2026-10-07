export const PERMISSIONS = {
    UserLihat: 'user.lihat',
    UserBuat: 'user.buat',
    UserUbah: 'user.ubah',
    UserNonaktifkan: 'user.nonaktifkan',
    UserKirimUlangAktivasi: 'user.kirim-ulang-aktivasi',

    RoleLihat: 'role.lihat',
    RoleBuat: 'role.buat',
    RoleUbah: 'role.ubah',
    RoleHapus: 'role.hapus',

    SekolahLihat: 'sekolah.lihat',
    SekolahBuat: 'sekolah.buat',
    SekolahUbah: 'sekolah.ubah',

    SiswaLihat: 'siswa.lihat',
    SiswaBuat: 'siswa.buat',
    SiswaUbah: 'siswa.ubah',
    SiswaHapus: 'siswa.hapus',
    SiswaImpor: 'siswa.impor',

    DokumenLihat: 'dokumen.lihat',
    DokumenUnggah: 'dokumen.unggah',
    DokumenHapus: 'dokumen.hapus',

    PendaftaranLihat: 'pendaftaran.lihat',
    PendaftaranBuat: 'pendaftaran.buat',
    PendaftaranAjukan: 'pendaftaran.ajukan',

    VerifikasiLihat: 'verifikasi.lihat',
    VerifikasiPutuskan: 'verifikasi.putuskan',

    JadwalLihat: 'jadwal.lihat',
    JadwalBuat: 'jadwal.buat',
    JadwalUbah: 'jadwal.ubah',

    RekamanLihat: 'rekaman.lihat',
    RekamanTandai: 'rekaman.tandai',

    LaporanLihat: 'laporan.lihat',

    LogLihat: 'log.lihat',
} as const;

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
