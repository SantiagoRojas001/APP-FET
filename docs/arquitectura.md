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
Docente registra (pasantía o práctica, semestre habilitado) ──promedio ≥ mínimo──▶ correo con usuario y contraseña
                                                           └─promedio < mínimo──▶ correo de ánimo (cuenta inactiva)
Estudiante: primer ingreso (cambia contraseña) ──▶ hoja de vida completa ──▶ EN_REVISION ──docente aprueba──▶ APROBADA
                                                                                  └─docente devuelve con recomendaciones─▶ CON_OBSERVACIONES
                                                                                    (el estudiante corrige y la envía de nuevo)

Empresa con acuerdo vigente ve las APROBADAS ──asigna cita──▶ PROGRAMADA ──según la cita, la empresa acepta──▶ ACEPTADO
                                                                │                                                 │
                                                                ├─empresa cancela─▶ CANCELADA                     │
                                                                └─empresa rechaza─▶ RECHAZADO                     │
                     director de programa envía la carta de presentación ──▶ PRESENTADO + vinculación ACTIVA ◀───┘
                     (tutor: el docente que aprobó la hoja de vida; carta de bienvenida al estudiante,
                      hoja de vida deshabilitada, demás citas canceladas)

Vinculación ACTIVA ──semanas aprobadas por la empresa suman las horas del programa (480)──▶ horas cumplidas (sigue ACTIVA)
       │                                                                                      │
       │                                                 empresa responde su evaluación ──────┴─▶ FINALIZADA (reconocida)
       └─ docente con ≥ 3 reportes ─▶ FINALIZADA si cumplió horas, si no CANCELADA («Terminada anticipadamente»)
```

1. **Estudiante**: lo registra un docente. Al primer ingreso cambia la contraseña que recibió por
   correo y acepta la política de datos. Mientras no cursa práctica ve Hoja de vida y Empresas
   interesadas: completa su hoja de vida en el formato institucional PA-GTH-F-10, la envía a
   revisión docente (si vuelve con recomendaciones, la corrige y la envía de nuevo) y ve las citas
   que le asignan las empresas (puede tener varias). Cuando empieza la práctica pasa al
   entorno de seguimiento: Mi pasantía, Horas semanales, Seguimiento (documentos) y Hoja de vida.
   Cada semana reporta sus horas con la evidencia y sube ARL, bitácora, control de asistencia e
   informes.
2. **Docente**: registra a los estudiantes cargando el listado de modalidades de grado (PDF, Excel
   o CSV): la app filtra a quienes eligieron pasantía o práctica en un semestre habilitado y el
   docente confirma la vista previa. Luego registra el promedio, que dispara el correo con el
   resultado (si el listado trae el promedio, el correo sale al confirmar). Revisa las hojas de vida: las aprueba (solo entonces las ven las empresas) o
   las devuelve con recomendaciones; quien la aprueba queda como tutor si el estudiante empieza la
   práctica. Ve las vacantes de las empresas y les recomienda estudiantes. Hace el seguimiento de
   las vinculaciones, revisa los documentos, evalúa al practicante y puede finalizar una práctica
   que acumule reportes.
3. **Empresa**: sube su acuerdo con la FET. Con el acuerdo aprobado y vigente revisa las hojas de
   vida aprobadas por los docentes (primero las que un docente le recomendó) y asigna citas. Según
   cada cita acepta al estudiante (con la fecha de inicio) o lo rechaza. Publica vacantes, que solo ven los docentes. Durante la práctica solo
   aprueba las horas semanales y, al final, evalúa al practicante: con esa evaluación la práctica
   culmina. Puede registrarse sola en `/empresasRegistro` y espera la validación de la FET.
4. **Director de programa**: consulta los acuerdos de las empresas (existentes y pendientes, sin
   aprobarlos), ve los estudiantes de su programa con su ciclo académico y si cursan pasantía,
   práctica o aún no, y envía la carta de presentación de los estudiantes que una empresa aceptó:
   con la carta empieza la práctica.
5. **Administrador**: crea las cuentas de docentes, directores y administradores, corrige los
   datos de los estudiantes, invita y valida empresas, aprueba o rechaza sus acuerdos y define los
   parámetros (promedio mínimo y semestres habilitados), las horas por programa y los periodos;
   atiende los reportes y consulta informes y la bitácora de auditoría.

Todos los roles reciben notificaciones (dentro de la app y por correo) y pueden reportar problemas.

## Reglas de negocio

Están en `app/Services` y las cubren las pruebas de `tests/Feature`.

- **Carga del listado de modalidades** (`ImportacionEstudiantesService`): lee PDF (reconstruye la
  tabla con la posición de cada texto; si no hay tabla, arma un registro por correo), Excel o CSV.
  Reconoce nombre, correo, programa, ciclo, modalidad y, si vienen, semestre, promedio, documento
  y código. Deja fuera a quien no eligió pasantía ni práctica, cursa un semestre no habilitado o
  ya tiene cuenta; si el listado trae el ciclo y no el semestre, toma el semestre habilitado del
  ciclo (técnico 3.º, tecnólogo 5.º, profesional 9.º). La vista previa no guarda nada; al
  confirmar se crean las cuentas.
- **Registro y habilitación** (`RegistroEstudianteService`, `ElegibilidadService`): solo se
  registran quienes eligieron pasantía o práctica y cursan un semestre habilitado (3.º, 5.º y 9.º
  por defecto). Registrar no envía correo. Al registrar el promedio (una sola vez) se guarda
  el resultado: con el promedio mínimo (4,2 por defecto) el estudiante queda habilitado, se le
  genera una contraseña y la recibe por correo (`CredencialesAcceso`); si no, su cuenta queda
  inactiva y recibe un mensaje de ánimo (`NoHabilitado`). Los parámetros los define la
  administración (tabla `parametro`).
- **Primer ingreso**: la cuenta con contraseña inicial (`usuario.debe_cambiar_password`) solo puede
  cambiarla (`POST /api/auth/primer-ingreso`, middleware `primer-ingreso`) y aceptar la política.
- **Revisión de la hoja de vida** (`HojaVidaService`): el estudiante la envía a revisión cuando
  está completa (100 %), está habilitado y no cursa práctica; se avisa a los docentes. El docente
  la aprueba o la devuelve con recomendaciones (obligatorias). Mientras está en revisión no se
  edita; si el estudiante edita una aprobada, vuelve a borrador y necesita otra aprobación. Las
  empresas solo encuentran hojas aprobadas.
- **Acuerdos** (`AcuerdoService`): la empresa sube su acuerdo en PDF con vigencia; la
  administración lo aprueba o lo rechaza con un motivo y el director lo consulta. Solo con un
  acuerdo aprobado y vigente la empresa asigna citas.
- **Citas** (`CitaService`): la empresa asigna una cita (fecha y hora de Colombia, dirección,
  indicaciones y vacante opcional) a un estudiante disponible, sin repetir un proceso abierto con
  él; el estudiante recibe la empresa, la dirección, la fecha y la hora (`CitaAsignada`). Después
  de la cita la empresa acepta (con la fecha de inicio) o rechaza. Si acepta, se avisa al director
  del programa (o a la administración si no hay director), que envía la carta de presentación
  (`CartaPresentacion`, adjunta al correo de la empresa): se crea la vinculación con el docente
  que aprobó la hoja de vida como tutor, el estudiante recibe la carta de bienvenida, su hoja de
  vida se deshabilita y sus demás citas abiertas se cancelan. Las decisiones bloquean la fila
  (`SELECT … FOR UPDATE`).
- **Recomendaciones** (`RecomendacionService`): un docente recomienda estudiantes disponibles
  para una vacante; la empresa los ve primero en Hojas de vida.
- **Empresas**: no pueden ingresar hasta que la FET las valide. Las que invita la administración
  quedan validadas; las que se registran en `/empresasRegistro` quedan `PENDIENTE`, se avisa a la
  administración y, al validarlas, a la empresa. Suspenderlas (`RECHAZADA`) cierra sus sesiones.
- **Cuentas** (`CuentaService`): docentes, directores y administradores no tienen registro público. La administración crea la cuenta con una
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
  los registros propios (una empresa solo encuentra sus vacantes y citas, un director solo su
  programa), `EstudiantePolicy` para los perfiles y
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
  registro y promedio de estudiantes, citas, acuerdos, revisiones de documentos, horas cumplidas, aprobación y finalización de prácticas,
  evaluaciones, reportes, cuentas, empresas, parámetros y periodos, con autor, IP y detalle.

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
| `GET /api/auth/yo` · `POST /api/auth/logout` | Autenticado | Usuario actual (incluye `tema`, `vinculacion_activa_id` y, para estudiantes, el `perfil` del encabezado: avance de la hoja de vida, habilitación y práctica en curso) y cierre de sesión |
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
| `POST /api/auth/primer-ingreso` | Estudiante con contraseña inicial | Cambia la contraseña y acepta la política |
| `POST /api/estudiante/hoja-vida/envio` | Estudiante | Envía la hoja de vida a revisión docente |
| `GET /api/estudiante/citas` · `/vinculaciones` | Estudiante | Empresas interesadas (citas) y prácticas propias |
| `POST /api/docente/importaciones` · `…/confirmacion` | Docente | Vista previa del listado de modalidades y creación de las cuentas |
| `GET /api/docente/estudiantes` · `PATCH …/{id}/promedio` | Docente | Estudiantes registrados y registro del promedio (envía el resultado) |
| `GET /api/docente/hojas-vida` · `PATCH …/{estudiante}` | Docente | Revisar hojas de vida: aprobar o devolver con recomendaciones |
| `GET /api/docente/vacantes` · `POST/DELETE …/{id}/recomendaciones` | Docente | Vacantes y recomendaciones |
| `GET /api/empresa/hojas-vida` | Empresa | Hojas de vida aprobadas, recomendadas primero |
| `GET/POST /api/empresa/citas` · `POST …/{id}/cancelacion` · `…/{id}/decision` | Empresa | Asignar y cancelar citas; aceptar o rechazar al estudiante |
| `GET/POST /api/empresa/acuerdos` | Empresa | Acuerdo con la FET |
| `GET /api/director/estudiantes` · `/acuerdos` · `GET/POST /cartas` | Director | Estudiantes del programa, acuerdos y cartas de presentación |
| `GET /api/citas/{id}/carta-presentacion.pdf` · `/api/acuerdos/{id}/archivo` | Según permisos | Carta de presentación y PDF del acuerdo |
| `GET/PATCH /api/docente/vinculaciones` | Docente | Listar (por estado y periodo) y asignarse como tutor |
| `GET/POST/PATCH /api/empresa/vacantes` | Empresa | Vacantes propias |
| `GET /api/empresa/vinculaciones` | Empresa | Practicantes |
| `GET /api/estudiantes` · `/{id}` | Docente, director, admin · también empresa (perfil) | Búsqueda y perfil de estudiantes |
| `GET/POST /api/vinculaciones/{id}/documentos` | Según permisos | Ver o subir documentos de seguimiento |
| `PATCH /api/documentos/{id}/revision` | Docente | Revisar un documento |
| `GET /api/documentos/{id}/archivo` | Según permisos | Descargar el PDF |
| `/api/admin/…` | Admin | Resumen, usuarios, estudiantes, empresas, acuerdos, parámetros, horas por programa, periodos, reportes, informes (y exportación a Excel) y auditoría |

La lista completa sale con `make artisan c="route:list --path=api"`.

## Ajustes al modelo de datos

El esquema respeta el diccionario de datos, con nombres, tipos, longitudes, valores por defecto y
llaves. Se agregaron:

1. **Sin postulaciones ni reglas de semestre mínimo**: las tablas `postulacion` y
   `regla_elegibilidad` se eliminaron; las reemplazan `cita` y `parametro`.
2. **`usuario.debe_cambiar_password` y `usuario.programa_id`** (programa que dirige un director).
3. **Sin contratación directa**: el modelo original contemplaba ofertas y vinculaciones por
   contratación directa. Se eliminó esa modalidad; solo hay pasantía y práctica profesional.
4. **`usuario.tema`** (`varchar(10)`, por defecto `system`): tema visual de cada cuenta, para que
   se conserve en cualquier equipo.
5. **Restricciones `CHECK`** en las columnas de estado y tipo (`rol`, `modalidad`, `estado`,
   `nivel`, etc.), con los valores de los enums de `app/Enums`.
6. **`programa.horas_requeridas`** (`smallint`, 480 por defecto).
7. **`vinculacion.periodo_id`, `horas_cumplidas_en`, `finalizada_por`**; `observacion_final`
   guarda la justificación del docente al finalizar.
8. **`estudiante.modalidad_grado`, `promedio`, `habilitado`, `resultado_enviado_en` y
   `registrado_por`**; `codigo` y `documento` pasan a ser opcionales. `hoja_vida.estado`,
   `observaciones`, `enviada_en`, `revisada_por` y `revisada_en` (revisión docente).
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
| `usuario.rol` | ADMIN, DOCENTE, ESTUDIANTE, EMPRESA, DIRECTOR |
| `vacante.modalidad`, `vinculacion.tipo`, `empresa.modalidad_interes`, `estudiante.modalidad_grado` | PASANTIA, PRACTICA |
| `empresa.estado_validacion` | PENDIENTE, VALIDADA, RECHAZADA |
| `vacante.estado` | ABIERTA, CERRADA |
| `hoja_vida.estado` | BORRADOR, EN_REVISION, APROBADA, CON_OBSERVACIONES |
| `cita.estado` | PROGRAMADA, ACEPTADO, RECHAZADO, PRESENTADO, CANCELADA |
| `acuerdo.estado` | PENDIENTE, APROBADO, RECHAZADO |
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
