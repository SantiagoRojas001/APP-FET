# Arquitectura de APP FET

## Visión general

Cliente y servidor separados:

- **Frontend** (`frontend/`): aplicación de una sola página en JavaScript sin framework. Vite sirve
  los archivos en desarrollo y reenvía `/api` al backend, así el navegador trabaja con un solo
  origen.
- **Backend** (`backend/`): Laravel expone una API REST JSON bajo `/api`. No genera HTML.
- **Base de datos**: PostgreSQL con las 15 tablas del documento *Modelado de la base de datos del
  aplicativo APP FET*.

## Roles y flujo principal

```
Estudiante ──postula──▶ ENVIADA ──docente aprueba──▶ APROBADA ──empresa acepta──▶ ACEPTADA ──▶ vinculación ACTIVA
                          │                             │
                          └─docente rechaza─▶ RECHAZADA └─empresa descarta─▶ DESCARTADA
```

1. **Estudiante**: tiene dos entornos. Mientras busca pasantía ve Vacantes, Mis postulaciones,
   Hoja de vida y Elegibilidad. Cuando una empresa lo acepta (vinculación activa) pasa al entorno
   de seguimiento: Mi pasantía, Seguimiento (documentos) y Hoja de vida. Completa su hoja de vida (al guardar ve la vista previa, tal como la ven las
   empresas), consulta las vacantes de empresas validadas y se postula si su semestre lo habilita.
2. **Docente**: revisa cada postulación con los requisitos a la vista y la aprueba o la rechaza con
   una observación. Hace el seguimiento de las vinculaciones y revisa los documentos.
3. **Empresa**: solo recibe postulaciones aprobadas por un docente. Al aceptar se crea la
   vinculación. Revisa los documentos de sus practicantes.
4. **Administrador**: valida empresas, define reglas de elegibilidad y gestiona cuentas.

## Reglas de negocio

Están en `app/Services` y las cubren las pruebas de `tests/Feature`.

- **Elegibilidad** (`ElegibilidadService`): un estudiante está habilitado para pasantía o práctica
  si su `semestre_actual` alcanza el `semestre_minimo` de la regla vigente de su programa
  (`regla_elegibilidad`). Si no lo está, o es egresado, no puede postularse. Solo existen dos
  modalidades: pasantía y práctica profesional.
- **Reglas con historial**: una regla no se edita. Al crear una nueva, la anterior se cierra el día
  antes (`vigente_hasta`).
- **Postulación** (`PostulacionService`): la vacante debe estar abierta y su empresa validada. El
  estudiante no puede tener otra vinculación activa ni postularse dos veces a la misma vacante.
  Las decisiones bloquean la fila (`SELECT … FOR UPDATE`) para evitar decisiones simultáneas.
- **Empresas**: no pueden ingresar hasta que la FET las valide.
- **Datos personales** (Ley 1581 de 2012): el registro exige aceptar la política y guarda la
  versión aceptada en `consentimiento_datos`. Las empresas no ven el documento de identidad.

## Seguridad

- Contraseñas con bcrypt. Tokens de Sanctum con vencimiento de 8 horas, revocados al cerrar sesión
  o al desactivar la cuenta.
- Control de acceso en tres niveles: middleware `rol:` por grupo de rutas, consultas limitadas a
  los registros propios (una empresa solo encuentra sus vacantes y postulaciones) y
  `VinculacionPolicy` para el seguimiento.
- Límite de 5 intentos de inicio de sesión por minuto, y de 5 solicitudes por minuto en la
  recuperación de contraseña.
- Recuperación de contraseña con el broker de Laravel: el token vence en 60 minutos, sirve una
  sola vez y se guarda cifrado. La respuesta es igual exista o no el correo, para no revelar
  cuentas. Al restablecer se cierran todas las sesiones abiertas.
- Los documentos se guardan en `storage/app/private` y solo se descargan por la API con permisos;
  la ruta interna nunca se expone.
- En el frontend, toda interpolación pasa por la plantilla `html` (`src/lib/html.js`), que escapa
  los datos por defecto contra XSS. Los enlaces a repositorios se validan como `https://`.
- En desarrollo, `Model::shouldBeStrict()` hace fallar las consultas N+1 y los atributos
  inexistentes.

## API

Todas las respuestas son JSON. Los enums se envían como `{ "valor": "APROBADA", "etiqueta": "Aprobada por el docente" }`.

| Método y ruta | Rol | Descripción |
| --- | --- | --- |
| `GET /api/catalogos` | Público | Programas, competencias y opciones de formularios |
| `POST /api/auth/login` | Público | Inicia sesión y devuelve el token |
| `POST /api/auth/olvide-contrasena` | Público | Envía el enlace para restablecer la contraseña |
| `POST /api/auth/restablecer-contrasena` | Público | Define la contraseña nueva con el token del enlace |
| `POST /api/registro/estudiante` · `/empresa` | Público | Registro con aceptación de la política (sin pantalla en el frontend, ver abajo) |
| `GET /api/auth/yo` · `POST /api/auth/logout` | Autenticado | Usuario actual (incluye `tema` y `vinculacion_activa_id`) y cierre de sesión |
| `PATCH /api/auth/preferencias` | Autenticado | Guarda el tema de la cuenta (`light`, `dark`, `system`) |
| `GET /api/proyectos/{id}/repositorio` | Dueño, empresa, docente, admin | Vista previa del repositorio de GitHub (caché de 30 min) |
| `GET/PUT /api/estudiante/perfil` | Estudiante | Hoja de vida completa |
| `GET /api/estudiante/elegibilidad` | Estudiante | Modalidades permitidas y requisitos |
| `GET /api/estudiante/vacantes` | Estudiante | Vacantes marcadas como disponibles o no |
| `POST /api/estudiante/vacantes/{id}/postulaciones` | Estudiante | Postularse |
| `GET /api/estudiante/postulaciones` · `/vinculaciones` | Estudiante | Seguimiento propio |
| `GET/PATCH /api/docente/postulaciones` | Docente | Revisar postulaciones |
| `GET/PATCH /api/docente/vinculaciones` | Docente | Asignarse como tutor y cerrar prácticas |
| `GET/POST/PATCH /api/empresa/vacantes` | Empresa | Vacantes propias |
| `GET/PATCH /api/empresa/postulaciones` | Empresa | Aceptar o descartar postulaciones aprobadas |
| `GET /api/empresa/vinculaciones` | Empresa | Practicantes |
| `GET /api/estudiantes` · `/{id}` | Empresa, docente, admin | Búsqueda y perfil de estudiantes |
| `GET/POST /api/vinculaciones/{id}/documentos` | Según permisos | Ver o subir documentos de seguimiento |
| `PATCH /api/documentos/{id}/revision` | Docente, empresa | Revisar un documento |
| `GET /api/documentos/{id}/archivo` | Según permisos | Descargar el PDF |
| `/api/admin/…` | Admin | Resumen, empresas, reglas y usuarios |

La lista completa sale con `make artisan c="route:list --path=api"`.

## Ajustes al modelo de datos

El esquema respeta el diccionario de datos, con nombres, tipos, longitudes, valores por defecto y
llaves. Se agregaron:

1. **`postulacion.observacion`** (`text`, opcional): motivo del docente al rechazar, que el
   estudiante ve en su lista de postulaciones.
2. **Único `(vacante_id, estudiante_id)` en `postulacion`**: evita postulaciones duplicadas.
3. **Sin contratación directa**: el modelo original contemplaba ofertas y vinculaciones por
   contratación directa. Se eliminó esa modalidad; solo hay pasantía y práctica profesional.
4. **`usuario.tema`** (`varchar(10)`, por defecto `system`): tema visual de cada cuenta, para que
   se conserve en cualquier equipo.
5. **Restricciones `CHECK`** en las columnas de estado y tipo (`rol`, `modalidad`, `estado`,
   `nivel`, etc.), con los valores de los enums de `app/Enums`.

Además están las tablas propias de Laravel: `personal_access_tokens` (Sanctum),
`password_reset_tokens`, `cache` y `jobs`.

## Valores de los catálogos

| Columna | Valores |
| --- | --- |
| `usuario.rol` | ADMIN, DOCENTE, ESTUDIANTE, EMPRESA |
| `vacante.modalidad`, `vinculacion.tipo`, `empresa.modalidad_interes` | PASANTIA, PRACTICA |
| `empresa.estado_validacion` | PENDIENTE, VALIDADA, RECHAZADA |
| `vacante.estado` | ABIERTA, CERRADA |
| `postulacion.estado` | ENVIADA, APROBADA, RECHAZADA, ACEPTADA, DESCARTADA |
| `vinculacion.estado` | ACTIVA, FINALIZADA, CANCELADA |
| `documento_seguimiento.tipo` | CONTROL_ASISTENCIA, DIARIO_CAMPO, INFORME_AVANCE, INFORME_FINAL |
| `documento_seguimiento.estado_revision` | PENDIENTE, APROBADO, OBSERVADO |
| `estudiante_competencia.nivel` | BASICO, INTERMEDIO, AVANZADO |

## Pendiente para una siguiente fase

- Definir cómo se crean las cuentas de estudiantes y empresas. La pantalla de registro se quitó
  del frontend, pero los endpoints `POST /api/registro/*` siguen activos.
- Notificaciones por correo cuando cambia el estado de una postulación.
- Exportar la hoja de vida a PDF en el formato institucional.
- Integración con la API de GitHub para mostrar la actividad de los repositorios.
- Configuración de despliegue para producción (servidor web, HTTPS, `APP_DEBUG=false`).
