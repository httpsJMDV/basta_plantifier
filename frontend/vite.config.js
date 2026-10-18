import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    envDir: fileURLToPath(new URL('../backend/', import.meta.url)),
    resolve: { alias: { '@': fileURLToPath(new URL('./src/', import.meta.url)) } },
    plugins: [
        laravel({
            input: ['src/app.tsx'],
            publicDirectory: '../backend/public',
            hotFile: fileURLToPath(new URL('../backend/public/hot', import.meta.url)),
            refresh: ['../backend/app/**', '../backend/routes/**', '../backend/resources/views/**', 'src/**'],
        }),
        react(),
        tailwindcss(),
    ],
    build: { outDir: '../backend/public/build', emptyOutDir: true },
    server: { host: '127.0.0.1', fs: { allow: ['..'] } },
});
