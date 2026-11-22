import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/taskforge/test/setup.ts'],
        include: ['resources/js/taskforge/**/*.test.ts', 'resources/js/taskforge/**/*.test.tsx'],
        clearMocks: true,
    },
});
