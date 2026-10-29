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
Estudiante ──postula──▶ ENVIADA ──empresa selecciona──▶ SELECCIONADA ──docente autoriza──▶ ACEPTADA ──▶ vinculación ACTIVA
 (la hoja de vida         │                                 │                                (carta de bienvenida,
  llega a la empresa)     └─empresa descarta─▶ DESCARTADA   └─docente no autoriza─▶ RECHAZADA  hoja de vida deshabilitada)

Vinculación ACTIVA ──semanas aprobadas por la empresa suman las horas del programa (480)──▶ horas cumplidas (sigue ACTIVA)
       │                                                                                      │
       │                                                 empresa responde su evaluación ──────┴─▶ FINALIZADA (reconocida)
       └─ docente con ≥ 3 reportes ─▶ FINALIZADA si cumplió horas, si no CANCELADA («Terminada anticipadamente»)
```

1. **Estudiante**: tiene dos entornos. Mientras busca pasantía ve Vacantes, Mis postulaciones,
   Hoja de vida y Elegibilidad. Cuando una empresa lo acepta (vinculación activa) pasa al entorno
   de seguimiento: Mi pasantía, Horas semanales, Seguimiento (documentos) y Hoja de vida. Completa
   su hoja de vida en el formato institucional PA-GTH-F-10 (al guardar ve la vista previa, tal como
   la ven las empresas), consulta las vacantes de empresas validadas y se postula si su semestre lo
   habilita. Cada semana reporta sus horas con la evidencia y sube ARL, bitácora, control de
   asistencia e informes.
2. **Docente**: recibe aviso cuando una empresa selecciona a un estudiante y autoriza la práctica
   (o la rechaza con una observación); al autorizar queda como tutor. Hace el seguimiento de las
   vinculaciones, revisa los documentos, evalúa al practicante y puede finalizar una práctica que
   acumule reportes.
3. **Empresa**: recibe directamente las hojas de vida de quienes se postulan a sus vacantes y
   selecciona o descarta. Su trabajo durante la práctica se limita a aprobar las horas semanales
   (una a una o todas las pendientes de una vez) y, al final, evaluar al practicante: con esa
   evaluación la práctica culmina. Consulta los documentos, pero no los revisa. Puede registrarse
   sola en `/empresasRegistro` y espera la validación de la FET.
4. **Administrador**: crea y edita cuentas, estudiantes y empresas, y valida las empresas que se
   registran solas. Define reglas
   de elegibilidad, horas por programa y periodos; atiende los reportes y consulta informes y la
   bitácora de auditoría.

Todos los roles reciben notificaciones (dentro de la app y por correo) y pueden reportar problemas.

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
  La empresa selecciona o descarta; al seleccionar se avisa a los docentes. El docente autoriza
  (con fecha de inicio) o no autoriza con un motivo. Al autorizar se crea la vinculación con él
  como tutor, las demás postulaciones abiertas del estudiante pasan a `DESCARTADA`, el estudiante
  recibe la carta de bienvenida en PDF (`CartaBienvenida`, adjunta al correo) y su hoja de vida se
  deshabilita: no se edita y no aparece en la búsqueda de las empresas mientras la práctica esté
  activa. Las decisiones bloquean la fila (`SELECT … FOR UPDATE`) para evitar decisiones simultáneas.
- **Empresas**: no pueden ingresar hasta que la FET las valide. Las que invita la administración
  quedan validadas; las que se registran en `/empresasRegistro` quedan `PENDIENTE`, se avisa a la
  administración y, al validarlas, a la empresa. Suspenderlas (`RECHAZADA`) cierra sus sesiones.
- **Cuentas** (`CuentaService`): estudiantes y docentes no tienen registro público. La administración crea la cuenta con una
  contraseña aleatoria y envía una invitación (broker `invitaciones`, tabla `invitacion_tokens`,
  7 días). Al activarla, la persona define su contraseña y acepta la política de datos; una cuenta
  sin consentimiento registrado figura como «invitación pendiente».
- **Horas y cierre** (`PracticaService`): el estudiante reporta una evidencia por semana (0,5 a 60
  horas, actividades y soporte opcional en PDF o foto, desde la semana de inicio y hasta hoy). La
  empresa la aprueba o la devuelve con un comentario sobre las horas; el estudiante corrige las
  devueltas y vuelven a quedar por revisar. Solo cuentan las horas aprobadas: cuando la suma
  alcanza `programa.horas_requeridas` (480 por defecto) se guarda `horas_cumplidas_en` y se avisa
  al estudiante, a la empresa y al tutor; la vinculación sigue activa. Cuando la empresa responde
  su evaluación con las horas cumplidas, la práctica queda `FINALIZADA` sin un paso de aprobación
  aparte y el estudiante recibe el aviso de que culminó. El docente tutor (o cualquiera, si no hay tutor) puede
  finalizarla con `fet.reportes_para_finalizar` (3) reportes ligados; si faltan horas debe
  justificarlo y queda `CANCELADA`, sin reconocimiento.
- **Evaluaciones** (`EvaluacionService`, `config/evaluaciones.php`): docente → practicante,
  empresa → practicante y practicante → empresa, con preguntas de opción múltiple y abiertas. Se
  habilitan con las horas cumplidas o la práctica cerrada; una por tipo y no se editan. Cada
  respuesta guarda el texto de su pregunta. La empresa no lee la evaluación que le hizo el
  practicante.
- **Reportes**: cualquier usuario reporta un problema; si es de una práctica propia, queda ligado a
  ella. Los reportes de una práctica solo los ven docentes y administración. La administración los
  atiende y quien reportó recibe la respuesta.
- **Periodos**: la vinculación queda en el periodo abierto que contiene su fecha de inicio. Al
  cerrar un periodo se cierran las vacantes abiertas.
- **Hoja de vida** (`HojaVidaService`, `pdf/hoja-vida.blade.php`): sigue el formato PA-GTH-F-10
  (datos personales y foto, formación académica, cursos, experiencia laboral, experiencia
  investigativa y referencias). El programa de la FET aparece solo como pregrado en curso; lo que
  no se registra sale como «No registra». El porcentaje mide datos personales, foto, contacto de
  emergencia, referencias (una familiar y una personal), perfil, competencias y repositorios.
- **Documentos**: los sube el estudiante (ARL, bitácora, control de asistencia, informes) y solo
  el docente los revisa.
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
- **Bitácora de auditoría** (`App\Services\Auditoria`, tabla `auditoria`): decisiones sobre
  postulaciones, revisiones de documentos, horas cumplidas, aprobación y finalización de prácticas,
  evaluaciones, reportes, cuentas, empresas, reglas y periodos, con autor, IP y detalle.

## API

Todas las respuestas son JSON. Los enums se envían como `{ "valor": "APROBADA", "etiqueta": "Aprobada por el docente" }`.

| Método y ruta | Rol | Descripción |
| --- | --- | --- |
| `GET /api/catalogos` | Público | Programas, competencias y opciones de formularios |
| `POST /api/auth/login` | Público | Inicia sesión y devuelve el token |
| `POST /api/auth/olvide-contrasena` | Público | Envía el enlace para restablecer la contraseña |
| `POST /api/auth/restablecer-contrasena` | Público | Define la contraseña nueva con el token del enlace |
| `POST /api/auth/activar-cuenta` | Público | Activa una cuenta invitada (contraseña y política de datos) |
| `POST /api/registro/empresa` | Público | Registro de una empresa (queda pendiente de validación) |
| `GET /api/auth/yo` · `POST /api/auth/logout` | Autenticado | Usuario actual (incluye `tema` y `vinculacion_activa_id`) y cierre de sesión |
| `PATCH /api/auth/preferencias` | Autenticado | Guarda el tema de la cuenta (`light`, `dark`, `system`) |
| `GET /api/notificaciones` · `PATCH …/leidas` · `PATCH …/{id}/leida` | Autenticado | Avisos dentro de la app |
| `GET/POST /api/reportes` | Autenticado | Reportar un problema y ver los propios |
| `GET/POST /api/vinculaciones/{id}/evidencias` | Según permisos | Horas semanales; solo el estudiante reporta |
| `POST /api/evidencias/{id}/correccion` | Estudiante | Corrige una semana devuelta |
| `PATCH /api/evidencias/{id}/revision` · `POST /api/vinculaciones/{id}/evidencias/aprobacion` | Empresa | Aprueba o devuelve una semana; aprueba todas las pendientes |
| `GET /api/evidencias/{id}/archivo` | Según permisos | Soporte de la semana |
| `GET /api/vinculaciones/{id}/carta-bienvenida.pdf` | Según permisos | Carta de bienvenida |
| `GET/POST /api/vinculaciones/{id}/evaluaciones` | Según permisos | Cuestionario del usuario y evaluaciones que puede leer |
| `GET /api/vinculaciones/{id}/reportes` | Docente, admin | Reportes ligados a la práctica |
| `GET /api/estudiante/hoja-vida.pdf` · `/api/estudiantes/{id}/hoja-vida.pdf` | Estudiante · empresa, docente, admin | Hoja de vida en PDF institucional |
| `POST /api/docente/vinculaciones/{id}/finalizacion` | Docente | Finalización anticipada |
| `GET /api/proyectos/{id}/repositorio` | Dueño, empresa, docente, admin | Vista previa del repositorio de GitHub (caché de 30 min) |
| `GET/PUT /api/estudiante/perfil` | Estudiante | Hoja de vida completa (formato PA-GTH-F-10) |
| `POST /api/estudiante/foto` · `GET /api/estudiantes/{id}/foto` | Estudiante · quien consulta perfiles | Foto de la hoja de vida |
| `GET /api/estudiante/elegibilidad` | Estudiante | Modalidades permitidas y requisitos |
| `GET /api/estudiante/vacantes` | Estudiante | Vacantes marcadas como disponibles o no |
| `POST /api/estudiante/vacantes/{id}/postulaciones` | Estudiante | Postularse |
| `GET /api/estudiante/postulaciones` · `/vinculaciones` | Estudiante | Seguimiento propio |
| `GET/PATCH /api/docente/postulaciones` | Docente | Autorizar (con fecha de inicio) o no autorizar a los seleccionados |
| `GET/PATCH /api/docente/vinculaciones` | Docente | Listar (por estado y periodo) y asignarse como tutor |
| `GET/POST/PATCH /api/empresa/vacantes` | Empresa | Vacantes propias |
| `GET/PATCH /api/empresa/postulaciones` | Empresa | Seleccionar o descartar postulaciones |
| `GET /api/empresa/vinculaciones` | Empresa | Practicantes |
| `GET /api/estudiantes` · `/{id}` | Empresa, docente, admin | Búsqueda y perfil de estudiantes |
| `GET/POST /api/vinculaciones/{id}/documentos` | Según permisos | Ver o subir documentos de seguimiento |
| `PATCH /api/documentos/{id}/revision` | Docente | Revisar un documento |
| `GET /api/documentos/{id}/archivo` | Según permisos | Descargar el PDF |
| `/api/admin/…` | Admin | Resumen, usuarios, estudiantes, empresas, reglas, horas por programa, periodos, reportes, informes (y exportación a Excel) y auditoría |

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
6. **`programa.horas_requeridas`** (`smallint`, 480 por defecto).
7. **`vinculacion.periodo_id`, `horas_cumplidas_en`, `finalizada_por`**; `observacion_final`
   guarda la justificación del docente al finalizar.
8. **`postulacion.revisada_en` y `decidida_en`**, para medir el tiempo de cada etapa.
9. **Tablas nuevas**: `periodo_academico`, `evidencia_semanal` (única por vinculación y semana;
   reemplazó a `asistencia`, cuyos registros se agruparon por semana como aprobados),
   `reporte`, `evaluacion` (única por vinculación y tipo, respuestas en `jsonb`) y `auditoria`.
10. **Hoja de vida PA-GTH-F-10**: `hoja_vida` suma foto, fecha y lugar de nacimiento, estado civil,
    residencia, dirección, R-H y contacto de emergencia (la `formacion` en texto libre se
    reemplazó); `experiencia` suma dirección, teléfono, jefe inmediato y dedicación; tablas
    `formacion_academica`, `curso`, `actividad_investigativa` y `referencia`.

Además están las tablas propias de Laravel: `personal_access_tokens` (Sanctum),
`password_reset_tokens`, `invitacion_tokens`, `notifications`, `cache` y `jobs`.

## Valores de los catálogos

| Columna | Valores |
| --- | --- |
| `usuario.rol` | ADMIN, DOCENTE, ESTUDIANTE, EMPRESA |
| `vacante.modalidad`, `vinculacion.tipo`, `empresa.modalidad_interes` | PASANTIA, PRACTICA |
| `empresa.estado_validacion` | PENDIENTE, VALIDADA, RECHAZADA |
| `vacante.estado` | ABIERTA, CERRADA |
| `postulacion.estado` | ENVIADA, SELECCIONADA, ACEPTADA, RECHAZADA, DESCARTADA |
| `vinculacion.estado` | ACTIVA, FINALIZADA, CANCELADA (se muestra como «Terminada anticipadamente») |
| `reporte.categoria` | FALLA_PLATAFORMA, PRACTICA, DATOS, OTRO |
| `reporte.estado` | ABIERTO, EN_REVISION, RESUELTO |
| `evaluacion.tipo` | DOCENTE_A_PRACTICANTE, EMPRESA_A_PRACTICANTE, PRACTICANTE_A_EMPRESA |
| `documento_seguimiento.tipo` | ARL, BITACORA, CONTROL_ASISTENCIA, INFORME_AVANCE, INFORME_FINAL |
| `documento_seguimiento.estado_revision`, `evidencia_semanal.estado` | PENDIENTE, APROBADO, OBSERVADO |
| `formacion_academica.nivel` | MAESTRIA, ESPECIALIZACION, PREGRADO, TECNOLOGO, TECNICO |
| `actividad_investigativa.tipo` | GRUPO, PROYECTO, TUTORIA_POSGRADO, TUTORIA_PREGRADO, PAR_EVALUADOR, INFORME_TECNICO, PUBLICACION, ORGANIZACION, PONENCIA |
| `referencia.tipo` | FAMILIAR, PERSONAL |
| `estudiante_competencia.nivel` | BASICO, INTERMEDIO, AVANZADO |

## Pendiente para una siguiente fase

- Inicio de sesión con la cuenta institucional (Microsoft o Google) si la FET lo habilita; hoy se
  restringe el dominio del correo.
- Carga masiva de estudiantes desde un archivo de la oficina de registro.
- Ajustar los cuestionarios a los formatos oficiales de la FET y las notas (60 % empresa, 40 % universidad).
- Convenio institución–empresa, carta de presentación, acta de inicio y plan de trabajo.
