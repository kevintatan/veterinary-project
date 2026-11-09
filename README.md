# Zooetia

Software para clínicas veterinarias en Colombia: agenda, historia clínica, tutores y mascotas, WhatsApp, consentimientos firmados, carné con QR, reserva en línea, portal del tutor, inventario, ventas y reportes. Varias clínicas comparten la misma instalación sin ver nunca los datos de las otras.

**¿Nuevo en el proyecto?** Instala todo con esta guía y después sigue con [`docs/README.md`](docs/README.md) (índice de la documentación y qué leer según lo que vas a hacer). Lo que hace la aplicación hoy está en [`docs/funcionalidades.md`](docs/funcionalidades.md).

## Estado

| Etapa | Estado |
|---|---|
| Fase 1 · Cimientos (clínicas, sedes, equipo, roles, prueba gratis) | ✅ Terminada |
| Fase 2 · Núcleo clínico (tutores, mascotas, agenda, historia clínica, tareas) | ✅ Terminada |
| Fase 3 · Panel web | ✅ Terminada |
| Fase 4 · WhatsApp, carné con QR, portal del tutor, consentimientos firmados | ✅ Terminada (falta conectar el número real de WhatsApp con Meta) |
| Registro de clínicas, planes, landing y reserva en línea | ✅ Terminada (falta el cobro en línea de la suscripción) |
| Fase 5a · Inventario, ventas, cobro desde la atención y reportes | ✅ Terminada |
| Fase 5b · Facturación electrónica DIAN | Pendiente (se define con un contador) |

El detalle de cada etapa está en [`docs/historial.md`](docs/historial.md).

## Estructura

| Carpeta | Qué es |
|---|---|
| `api/` | API en Laravel 13 (PHP 8.4), PostgreSQL, pruebas con Pest |
| `web/` | Aplicación web en Next.js 16: panel de la clínica, landing, reserva en línea y portal del tutor |
| `docs/` | Documentación del producto y técnica |

La web nunca llama a Laravel desde el navegador: pasa por su propio servidor (`/api/backend/*`), que guarda la sesión en cookies seguras.

## 1. Requisitos

| Programa | Versión | Notas |
|---|---|---|
| **PHP** | 8.4 | Extensiones: `pdo_pgsql`, `pgsql`, `gd`, `intl`, `mbstring`, `bcmath`, `fileinfo`, `zip`, `openssl`, `curl`. En Windows se activan en `php.ini` quitando el `;` de `extension=...`. Revisa con `php -m`. |
| **Composer** | 2.x | https://getcomposer.org |
| **Node.js** | 22 | Trae `npm`. |
| **PostgreSQL** | 14 o superior | Cualquier instalación sirve (instalador oficial, Docker, portable). |
| **Git** | — | Acceso al repositorio privado. |

## 2. Base de datos

Se usan **dos bases** en el mismo servidor PostgreSQL:

- `zooetia_dev`: la que usa la aplicación.
- `zooetia_test`: la usan las pruebas automáticas, que **borran y recrean todo** en cada corrida. Por seguridad, las pruebas se niegan a correr si el nombre de la base no termina en `_test`.

Desde `psql` (o pgAdmin), con el usuario administrador de PostgreSQL:

```sql
CREATE USER zooetia WITH PASSWORD 'elige-una-contraseña';
CREATE DATABASE zooetia_dev OWNER zooetia;
CREATE DATABASE zooetia_test OWNER zooetia;
```

Las migraciones activan la extensión `pg_trgm` (búsqueda tolerante a errores); como `zooetia` es dueño de las bases, puede hacerlo sin permisos extra.

## 3. API (Laravel)

```bash
cd api
composer install
cp .env.example .env          # en PowerShell: copy .env.example .env
php artisan key:generate
```

Edita `api/.env` con los datos de tu PostgreSQL:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5432                  # el puerto de tu PostgreSQL
DB_DATABASE=zooetia_dev
DB_USERNAME=zooetia
DB_PASSWORD=la-contraseña-que-elegiste

DB_TEST_HOST=127.0.0.1
DB_TEST_PORT=5432             # normalmente el mismo puerto
DB_TEST_DATABASE=zooetia_test
DB_TEST_USERNAME=zooetia
DB_TEST_PASSWORD=la-contraseña-que-elegiste
```

El resto del `.env` ya viene listo para local: correos al log (`MAIL_MAILER=log`), WhatsApp simulado (`WHATSAPP_DRIVER=fake`, no sale ningún mensaje real) y colas y caché en la base de datos (sin Redis).

Crea las tablas con datos de demostración y arranca la API:

```bash
php artisan migrate --seed    # planes, roles, catálogos y clínicas demo
php artisan serve --port=8010 # comprueba: http://localhost:8010/api/v1/health
```

## 4. Web (Next.js)

En otra terminal:

```bash
cd web
npm install
cp .env.example .env.local    # en PowerShell: copy .env.example .env.local
npm run dev                   # http://localhost:3000
```

`.env.local` solo tiene `API_URL=http://localhost:8010/api/v1`; cámbialo si la API corre en otro puerto.

## 5. Entrar

Abre http://localhost:3000/login. Todos los usuarios demo usan la contraseña `password`:

| Correo | Qué es |
|---|---|
| `admin@anipet.test` | Administradora de *Veterinaria Anipet* (dos sedes) |
| `vet@anipet.test` | Veterinario en Anipet **y** administrador en *Clínica Huellitas* (elige clínica al entrar) |
| `recepcion@anipet.test` | Recepción de Anipet, solo Sede Cabecera |

Portal del tutor: http://localhost:3000/portal/ingresar con `paola@anipet.test` / `password1`.

Para probar el registro de una clínica nueva: http://localhost:3000/registro.

Estos usuarios solo existen en local: los datos de demostración se niegan a crearse en producción.

## 6. Procesos en segundo plano (opcional)

Para que funcionen los envíos (WhatsApp simulado, correos al log), los recordatorios automáticos y las tareas diarias, abre dos terminales más en `api/`:

```bash
php artisan queue:work        # procesa los envíos
php artisan schedule:work     # tareas programadas: recordatorios, vencimientos, alertas de inventario
```

Sin ellos la aplicación funciona igual; solo no salen los envíos automáticos.

## 7. Pruebas y calidad

```bash
cd api
./vendor/bin/pest             # pruebas de la API (usan zooetia_test)
./vendor/bin/pint             # formato del código PHP

cd web
npm run typecheck && npm run lint
```

Antes de unir una rama a `master`, todas las pruebas deben pasar.

## Comandos útiles

```bash
php artisan migrate:fresh --seed                  # borra zooetia_dev y la recrea con datos demo
php artisan db:seed --class=DemoTodaySeeder       # vuelve a crear las citas de hoy
php artisan db:seed --class=DemoRemindersSeeder   # citas de mañana y vacunas por vencer
php artisan route:list --path=api/v1              # todas las rutas de la API
```

## Problemas comunes

| Síntoma | Causa y solución |
|---|---|
| `could not find driver` | Falta la extensión `pdo_pgsql` en `php.ini`. |
| `Call to undefined function imagecreatefromstring` | Falta la extensión `gd` (fotos, logos y firmas). |
| Las pruebas dicen que no corren en esa base | `DB_TEST_DATABASE` debe terminar en `_test`. |
| La web carga pero no entra / error 500 en el login | La API no está corriendo en el puerto de `API_URL`. |
| `permission denied to create extension "pg_trgm"` | El usuario `zooetia` no es dueño de la base: `ALTER DATABASE zooetia_dev OWNER TO zooetia;` (igual para `zooetia_test`). |
| En Windows, `npm install` falla en Git Bash | Ejecútalo desde PowerShell. |

## Reglas del repositorio

- Nunca se suben `.env`, contraseñas, tokens ni llaves (ya están en `.gitignore`).
- Una rama por fase o tarea; se une a `master` cuando todas las pruebas pasan.
- Cada funcionalidad nueva se documenta en `docs/funcionalidades.md` y `docs/historial.md`.

Más detalle del día a día (ramas, commits, recetas) en [`docs/desarrollo.md`](docs/desarrollo.md).
