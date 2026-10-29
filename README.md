# APP FET · Agencia para Practicantes y Profesionales FET

Aplicativo web institucional que conecta a estudiantes, docentes y empresas durante el proceso de
prácticas y pasantías de la Fundación Escuela Tecnológica de Neiva (FET): hoja de vida en formato
institucional, vacantes, aprobación docente y seguimiento con documentos.

Proyecto de aula de José Santiago Rojas y Carlos Stiven López (Ingeniería de Software).

| Capa | Tecnología |
| --- | --- |
| Frontend | HTML5, CSS3 y JavaScript (módulos ES) con Vite |
| Backend | PHP 8.4, Laravel 13, API REST |
| Seguridad | Laravel Sanctum (tokens), bcrypt, control de acceso por roles |
| Base de datos | PostgreSQL 17 |
| Entorno | Docker Compose |

## Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) encendido.
- Node.js 20 o superior (opcional: solo para que ESLint funcione dentro de VS Code).

No necesitas instalar PHP, Composer ni PostgreSQL en tu equipo: corren en contenedores.

## Puesta en marcha

```bash
# 1. Variables de entorno del backend (solo la primera vez)
cp backend/.env.example backend/.env

# 2. Levantar base de datos, API y frontend
docker compose up -d

# 3. Dependencias, llave y base de datos con datos de ejemplo (solo la primera vez)
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed
```

Abre **http://localhost:5173**.

| Servicio | Dirección |
| --- | --- |
| Aplicación web | http://localhost:5173 |
| API | http://localhost:8000/api |
| Correos de prueba (Mailpit) | http://localhost:8025 |
| PostgreSQL (pgAdmin, DBeaver…) | `localhost:5433`, base `app_fet`, usuario y clave `app_fet` |

Para apagar todo: `docker compose down` (los datos se conservan en un volumen).

### Probar desde otro equipo (misma red Wi-Fi)

1. Con el proyecto encendido, ejecuta `make ip` (o `ipconfig getifaddr en0`) para ver la IP del Mac.
2. En el otro equipo, conectado a la misma red, abre `http://<IP>:5173`.
3. Si macOS pregunta si permite conexiones entrantes para Docker, acepta.
4. Para que los enlaces de recuperación de contraseña funcionen desde ese equipo, cambia
   `FRONTEND_URL=http://<IP>:5173` en `backend/.env` (y vuelve a `localhost` al terminar).

La IP puede cambiar al reconectarte al Wi-Fi; si deja de abrir, consúltala de nuevo.

### Recuperar contraseña

En la pantalla de ingreso, **¿Olvidaste tu contraseña?** envía un enlace al correo de la cuenta.
En desarrollo ningún correo sale a internet: todos llegan a Mailpit (http://localhost:8025), donde
puedes abrir el enlace. En producción se configura un servidor SMTP real en `backend/.env`
(`MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`) y `FRONTEND_URL` con el dominio público.

### Vista previa de GitHub

Los proyectos de la hoja de vida muestran una vista previa del repositorio (descripción, lenguajes,
estrellas y últimos commits), consultada a la API pública de GitHub y guardada en caché 30 minutos.
Sin token el límite es de 60 consultas por hora; para ampliarlo, crea un token personal sin permisos
en GitHub y ponlo en `GITHUB_TOKEN` de `backend/.env`. Los proyectos de ejemplo apuntan a
repositorios públicos conocidos solo para la demostración.

### Cuentas de ejemplo

Todas usan la contraseña `demo1234`. Solo existen fuera de producción.

| Rol | Correo | Situación |
| --- | --- | --- |
| Estudiante | mateo.suarez@fet.edu.co | 9.º semestre, habilitado para pasantía |
| Estudiante | laura.mendez@fet.edu.co | 5.º semestre, aún no habilitada para postularse |
| Estudiante | andres.rojas@fet.edu.co | Pasantía activa con 472 de 480 horas y 3 reportes: al registrar una jornada más completa las horas |
| Estudiante | valentina.torres@fet.edu.co | Pasantía con las horas cumplidas, esperando la aprobación de la empresa |
| Docente | p.vargas@fet.edu.co | Revisa postulaciones y seguimiento |
| Empresa | talento@nododigital.co | Empresa validada (practicante: Andrés) |
| Empresa | ti@clinicasangabriel.co | Puede evaluar y aprobar la pasantía de Valentina |
| Empresa | rrhh@agrosur.co | Pendiente de validación (no puede ingresar) |
| Administrador | admin@fet.edu.co | Cuentas, empresas, reglas, periodos, informes, reportes y auditoría |

### Cuentas y acceso

Estudiantes y docentes no tienen registro público: la administración crea sus cuentas y las de
administradores (con correo `@fet.edu.co`) e invita a las empresas. Las empresas también pueden
registrarse solas en `/empresasRegistro` (por ejemplo, `http://localhost:5173/empresasRegistro`)
y ingresan cuando la administración las valida. Cada cuenta nueva recibe un
correo con el enlace para definir la contraseña y aceptar la política de datos; el enlace dura
7 días y se puede reenviar desde Usuarios o Empresas. En un servidor nuevo, la primera cuenta de
administración se crea con `php artisan fet:crear-admin correo@fet.edu.co`.

## Comandos frecuentes

Con `make` (incluido en macOS):

| Comando | Qué hace |
| --- | --- |
| `make up` / `make down` | Levanta o detiene el entorno |
| `make test` | Ejecuta las pruebas del backend |
| `make lint` | Revisa estilo (Pint y ESLint) |
| `make format` | Corrige estilo |
| `make fresh` | Reconstruye la base de datos con datos de ejemplo |
| `make artisan c="route:list"` | Cualquier comando de Artisan |

Para producción (HTTPS, respaldos diarios) ver [docs/despliegue.md](docs/despliegue.md).

## Estructura

```
APPfet/
├── backend/                Laravel (API REST)
│   ├── app/Enums/          Estados y catálogos del dominio (rol, modalidad, estados…)
│   ├── app/Http/           Controladores por rol, Form Requests y Resources
│   ├── app/Models/         Un modelo por tabla del modelo de datos
│   ├── app/Policies/       Permisos sobre vinculaciones y documentos
│   ├── app/Services/       Reglas de negocio (elegibilidad, postulación, horas y cierre, evaluaciones, informes)
│   ├── config/evaluaciones.php  Cuestionarios de cierre de la práctica
│   ├── database/           Migraciones, factories y seeders
│   ├── routes/api.php      Todas las rutas de la API
│   └── tests/Feature/      Pruebas de la API
├── frontend/               Interfaz web
│   └── src/
│       ├── api/            Cliente HTTP
│       ├── lib/            Plantillas seguras, componentes de interfaz, formato
│       ├── views/          Pantallas por rol
│       └── routes.js       Menú y rutas por rol
├── docker/                 Imagen de PHP, base de pruebas y archivos de producción
├── docs/                   Arquitectura y prototipo original
├── docker-compose.yml      Entorno de desarrollo
└── docker-compose.prod.yml Producción
```

Más detalle en [docs/arquitectura.md](docs/arquitectura.md).
