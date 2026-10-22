# APP FET

Monorepo: `backend/` (Laravel 13, API REST con Sanctum, PostgreSQL) y `frontend/` (JavaScript con
módulos ES y Vite, sin framework). Todo el código, los mensajes y los nombres del dominio están en
español, igual que las tablas del modelo de datos (`usuario`, `vacante`, `postulacion`…).

## Comandos

PHP no está instalado en el equipo: todo comando de PHP o Composer se ejecuta en el contenedor.

- Levantar: `docker compose up -d` (web en :5173, API en :8000, Postgres en :5433, Mailpit en :8025)
- Pruebas: `docker compose exec api php artisan test` (usa la base `app_fet_test`)
- Estilo PHP: `docker compose exec api ./vendor/bin/pint`
- Frontend: `cd frontend && npm run lint && npm run build`
- Reiniciar datos: `docker compose exec api php artisan migrate:fresh --seed`

## Convenciones

- Las reglas de negocio van en `app/Services`, no en controladores. Una violación de regla lanza
  `ReglaNegocioException` (respuesta 422).
- Validación en Form Requests; salida JSON siempre por API Resources (los enums como `{valor, etiqueta}`).
- Los estados son enums en `app/Enums` y la base los restringe con `CHECK` (`App\Support\Restricciones`).
- Las consultas de empresa y estudiante se limitan a sus propios registros; las vinculaciones usan `VinculacionPolicy`.
- `Model::shouldBeStrict()` está activo fuera de producción: carga las relaciones con `with()`.
- Frontend: interpola siempre con la plantilla `html` de `src/lib/html.js` (escapa por defecto);
  usa `raw()` solo con contenido propio. Cada vista exporta `async function (ctx)` y se registra en `src/routes.js`.
- Colores: solo con los tokens de `frontend/src/styles/main.css`. Fondos y superficies en grises neutros; el verde institucional (`--accent`, menta `--accent-soft`) solo en detalles (botones, enlaces, menú activo, etiquetas). Nunca hex sueltos en las vistas.
- Diseño plano: no envolver secciones en tarjetas ni anidar recuadros. Usa `.panel` (sección con línea superior) y listas con divisores.
- Ver `docs/arquitectura.md` antes de cambiar el modelo de datos o el flujo de postulación.
