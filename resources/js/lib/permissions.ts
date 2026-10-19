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
    SekolahTautan: 'sekolah.tautan',
    SekolahResetTautan: 'sekolah.reset-tautan',

    SiswaLihat: 'siswa.lihat',
    SiswaUbah: 'siswa.ubah',
    SiswaHapus: 'siswa.hapus',

    DokumenLihat: 'dokumen.lihat',
    DokumenUnggah: 'dokumen.unggah',
    DokumenHapus: 'dokumen.hapus',

    PendaftaranLihat: 'pendaftaran.lihat',
    PendaftaranBuat: 'pendaftaran.buat',

    VerifikasiLihat: 'verifikasi.lihat',
    VerifikasiPutuskan: 'verifikasi.putuskan',

    JadwalLihat: 'jadwal.lihat',
    JadwalBuat: 'jadwal.buat',
    JadwalUbah: 'jadwal.ubah',

    RekamanLihat: 'rekaman.lihat',
    RekamanTandai: 'rekaman.tandai',
    RekamanPindai: 'rekaman.pindai',

    LaporanLihat: 'laporan.lihat',

    LogLihat: 'log.lihat',

    ProfilLihat: 'profil.lihat',
    ProfilUbah: 'profil.ubah',
} as const;

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
