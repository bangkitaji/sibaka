/// <reference types="vitest" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
      refresh: true,
    }),
    react(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./resources/js/test-setup.ts'],
    include: ['resources/js/**/*.{test,spec}.{ts,tsx}', 'tests/js/**/*.{test,spec,property}.{ts,tsx}'],
  },
});
