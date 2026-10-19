function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * POST JSON ke endpoint Laravel (token XSRF dari cookie). Status 4xx/5xx dikembalikan, bukan dilempar;
 * hanya galat jaringan yang melempar. `data` null bila badan respons bukan JSON.
 */
export async function postJson<T>(
    url: string,
    body: unknown,
): Promise<{ status: number; data: T | null }> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => null);

    return { status: response.status, data };
}
