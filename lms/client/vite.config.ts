import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  base: '/exam_portal/lms/public/',
  build: {
    outDir: '../public',
    sourcemap: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/exam_portal': {
        target: 'http://localhost',
        changeOrigin: true,
      },
    },
  },
});
