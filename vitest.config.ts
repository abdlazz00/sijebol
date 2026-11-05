import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.ts'],
        // Setiap berkas membuat lingkungan jsdom; di mesin yang sibuk (atau runner CI kecil) tes yang tidak
        // berkaitan melewati 5 detik bawaan padahal lulus bila dijalankan sendiri.
        testTimeout: 15_000,
    },
});
