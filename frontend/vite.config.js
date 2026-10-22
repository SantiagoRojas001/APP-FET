import { defineConfig } from 'vite';

// En desarrollo, /api se reenvía al backend Laravel: el navegador ve un solo
// origen y no hace falta configurar CORS.
export default defineConfig({
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: process.env.VITE_API_PROXY ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
});
