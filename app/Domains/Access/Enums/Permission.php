<?php

namespace App\Domains\Access\Enums;

enum Permission: string
{
    case UserLihat = 'user.lihat';
    case UserBuat = 'user.buat';
    case UserUbah = 'user.ubah';
    case UserNonaktifkan = 'user.nonaktifkan';
    case UserKirimUlangAktivasi = 'user.kirim-ulang-aktivasi';

    case RoleLihat = 'role.lihat';
    case RoleBuat = 'role.buat';
    case RoleUbah = 'role.ubah';
    case RoleHapus = 'role.hapus';

    case SekolahLihat = 'sekolah.lihat';
    case SekolahBuat = 'sekolah.buat';
    case SekolahUbah = 'sekolah.ubah';
    case SekolahHapus = 'sekolah.hapus';

    case SiswaLihat = 'siswa.lihat';
    case SiswaUbah = 'siswa.ubah';
    case SiswaHapus = 'siswa.hapus';

    case DokumenLihat = 'dokumen.lihat';
    case DokumenUnggah = 'dokumen.unggah';
    case DokumenHapus = 'dokumen.hapus';

    case PendaftaranLihat = 'pendaftaran.lihat';
    case PendaftaranBuat = 'pendaftaran.buat';

    case VerifikasiLihat = 'verifikasi.lihat';
    case VerifikasiPutuskan = 'verifikasi.putuskan';

    case JadwalLihat = 'jadwal.lihat';
    case JadwalBuat = 'jadwal.buat';
    case JadwalUbah = 'jadwal.ubah';

    case RekamanLihat = 'rekaman.lihat';
    case RekamanTandai = 'rekaman.tandai';
    case RekamanPindai = 'rekaman.pindai';

    case LaporanLihat = 'laporan.lihat';

    case LogLihat = 'log.lihat';

    case ProfilLihat = 'profil.lihat';
    case ProfilUbah = 'profil.ubah';

    case SekolahTautan = 'sekolah.tautan';
    case SekolahResetTautan = 'sekolah.reset-tautan';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
