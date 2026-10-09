import type { ImportStatus } from '@/types/student';
import { onScopeDispose, ref } from 'vue';

const POLL_MS = 2000;
const STALLED_MS = 20000;

async function fetchStatus(id: number): Promise<ImportStatus> {
    const response = await fetch(route('siswa.impor.show', id), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    if (!response.ok) throw new Error(`Status ${response.status}`);

    return (await response.json()) as ImportStatus;
}

const isDone = (s: ImportStatus) =>
    s.status === 'selesai' || s.status === 'gagal';

/** Memantau impor di queue lewat polling; berhenti saat selesai/gagal dan saat cakupan dibuang. */
export function useImportStatus(
    fetcher: (id: number) => Promise<ImportStatus> = fetchStatus,
) {
    const status = ref<ImportStatus | null>(null);
    const stalled = ref(false);
    let timer: ReturnType<typeof setInterval> | null = null;
    let startedAt = 0;

    function stop() {
        if (timer !== null) clearInterval(timer);
        timer = null;
    }

    async function tick(id: number) {
        try {
            const next = await fetcher(id);
            status.value = next;
            stalled.value =
                next.status === 'antri' && Date.now() - startedAt > STALLED_MS;
            if (isDone(next)) stop();
        } catch {
            // Gangguan sesaat: polling berikutnya mencoba lagi.
        }
    }

    function start(id: number) {
        stop();
        startedAt = Date.now();
        stalled.value = false;
        status.value = {
            id,
            status: 'antri',
            imported: 0,
            failed: [],
            error: null,
        };
        void tick(id);
        timer = setInterval(() => void tick(id), POLL_MS);
    }

    /** Menampilkan hasil yang sudah ada; hanya memantau bila belum selesai. */
    function show(initial: ImportStatus) {
        stop();
        status.value = initial;
        if (!isDone(initial)) start(initial.id);
    }

    onScopeDispose(stop);

    return { status, stalled, start, show, stop };
}
