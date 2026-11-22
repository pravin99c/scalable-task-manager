import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/taskforge/main.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/taskforge/test/setup.ts'],
        include: ['resources/js/taskforge/**/*.test.ts', 'resources/js/taskforge/**/*.test.tsx'],
    },
});
