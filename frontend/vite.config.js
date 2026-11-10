import { createReadStream, existsSync, readdirSync, readFileSync } from 'node:fs';
import { basename, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

/**
 * Archivos WebAssembly del detector de rostros (MediaPipe), que revisa la
 * foto de la hoja de vida en el navegador. Se sirven desde node_modules en
 * /mediapipe/ (en desarrollo) y se copian al build, sin guardarlos en el
 * repositorio. El modelo del detector sí está en public/mediapipe/.
 */
function mediapipeWasm() {
  const dir = fileURLToPath(new URL('./node_modules/@mediapipe/tasks-vision/wasm', import.meta.url));
  return {
    name: 'mediapipe-wasm',
    configureServer(server) {
      server.middlewares.use('/mediapipe', (req, res, next) => {
        const archivo = join(dir, basename(req.url.split('?')[0]));
        if (!existsSync(archivo)) return next();
        res.setHeader('Content-Type', archivo.endsWith('.wasm') ? 'application/wasm' : 'text/javascript');
        createReadStream(archivo).pipe(res);
      });
    },
    generateBundle() {
      for (const nombre of readdirSync(dir)) {
        this.emitFile({ type: 'asset', fileName: `mediapipe/${nombre}`, source: readFileSync(join(dir, nombre)) });
      }
    },
  };
}

// En desarrollo, /api se reenvía al backend Laravel: el navegador ve un solo
// origen y no hace falta configurar CORS.
export default defineConfig({
  plugins: [mediapipeWasm()],
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
