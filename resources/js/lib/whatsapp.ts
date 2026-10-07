/** Tautan wa.me berisi pesan siap kirim; null bila nomor tidak dapat dipakai. */
export function whatsappUrl(
    phone: string | null | undefined,
    link: string,
    name: string,
): string | null {
    if (!phone) return null;

    const digits = phone.replace(/\D/g, '');
    const international = digits.startsWith('62')
        ? digits
        : digits.startsWith('0')
          ? `62${digits.slice(1)}`
          : null;

    if (!international || !/^628\d{8,12}$/.test(international)) return null;

    const message = `Halo ${name}, berikut link aktivasi akun SIJEBOL Anda: ${link}`;

    return `https://wa.me/${international}?text=${encodeURIComponent(message)}`;
}
