# Guía de desarrollo

Cómo trabajamos en el día a día. Para instalar todo por primera vez, sigue [`../README.md`](../README.md).

## 1. Arrancar a trabajar (cada día)

```powershell
# 1. PostgreSQL encendido

# 2. API (otra terminal)
cd api; php artisan serve --port=8010

# 3. Web (otra terminal, desde PowerShell)
cd web; npm run dev            # http://localhost:3000
```

Entra con `admin@anipet.test` / `password` (usuarios demo en el README).

**Clínica creada desde el registro** (`/registro`, para probar "Primeros pasos" y el plan): Veterinaria Patitas, `laura@patitas.test` / `Patitas2026`.

**Portal del tutor** (`/portal/ingresar`): `paola@anipet.test` / `password1` (dueña de Rocky en Anipet; `DemoPortalSeeder`). Para probar la activación desde cero: ficha de cualquier tutor con celular → *Portal del tutor* → *Copiar enlace* y abrirlo en otra ventana privada.

Si vas a probar recordatorios automáticos (Fase 4c; detalle en [`whatsapp.md`](whatsapp.md)), además:

```powershell
cd api; php artisan queue:work     # procesa los jobs (envíos)
cd api; php artisan schedule:work  # dispara las tareas programadas cada minuto
```

**Datos demo frescos:** `php artisan migrate:fresh --seed` recrea todo (planes, roles, catálogos, la clínica *Veterinaria Anipet* con tutores, mascotas, 45 días de citas y citas de hoy). `php artisan db:seed --class=DemoTodaySeeder` solo vuelve a crear las citas del día.

## 2. Ramas y commits

- `master` siempre funciona: suite de tests en verde y build del frontend en verde.
- **Una rama por fase** (`fase-3-dashboard`, `fase-4-diferenciadores`). Dentro de la fase, un commit por paso terminado (3a, 3b…).
- Se fusiona a `master` con `git merge --no-ff` **solo** cuando todo está verde, y se sube a GitHub (`origin`, repo privado).
- Mensajes de commit en español, con un título corto y un cuerpo que explique **qué y por qué** (el historial es documentación). Ejemplo: `git log --oneline` y luego `git show <hash>`.
- Nunca se suben `.env`, contraseñas ni tokens.

## 3. Antes de dar algo por terminado

```bash
# API
cd api
./vendor/bin/pint            # formato
./vendor/bin/pest            # ~180 tests, ~40 s

# Web (desde PowerShell)
cd web
npm run typecheck
npm run lint
npx next build               # al cerrar un paso grande
```

Y **probar el flujo en el navegador** (claro y oscuro): crear, editar, el caso de error (p. ej. una cita cruzada) y que otra clínica o un rol sin permiso no lo vea.

## 4. Recetas

### Agregar un endpoint

1. **Ruta** en `api/routes/api.php`, dentro del grupo de permiso correcto (y dentro de `writable` si escribe).
2. **Validación** en un `FormRequest` (`app/Http/Requests/<Área>/…`) con nombres de campos en español.
3. **Lógica** en una *Action* (`app/Actions/<Área>/…`) si es más que leer y guardar.
4. **Respuesta** con un `JsonResource`: fechas con `ClinicTime::iso($fecha, $clinic)`, enums como `{value, label}`.
5. **Tests** en `tests/Feature/<Área>/…`: el caso feliz, la validación, **el permiso** (un rol que no debe poder) y **el aislamiento** (otra clínica recibe 404).
6. Documentar la ruta en la tabla de endpoints del README.

### Agregar una tabla de negocio

- Migración con `$table->clinic()` y, si otras tablas la referencian, `$table->tenantKey()`; las FKs a otras tablas de la clínica con `$table->tenantForeign(...)`.
- Modelo con `use BelongsToClinic, HasUuids, StoresDatesInUtc`.
- `SchemaTest` fallará si olvidas `clinic_id`: es a propósito.
- Explicar la tabla en `docs/database.md`.

### Agregar un tipo de registro clínico

Una clase en `api/app/Clinical/Types/` (reglas de validación, cómo se guarda, cómo se presenta y su resumen) + su tabla de detalle + registrarlo en `RecordTypes::TYPES`. En el frontend: el formulario en `RecordFormPanel.tsx`, la vista en `ClinicalHistory.tsx` (`DetailView`) y el bloque del PDF en `resources/views/pdf/partials/record.blade.php`.

### Agregar una pantalla

1. Página en `web/src/app/app/<ruta>/page.tsx` (componente de servidor, define el `title`).
2. La lógica interactiva en un componente cliente al lado (`"use client"`). Si lee la URL (`useSearchParams`), envolverlo en `<Suspense>`.
3. Datos con React Query y `api()`; tipos en `lib/`.
4. Formularios con `components/form/fields.tsx` y `SidePanel` para crear/editar.
5. Entrada en el menú (`layout/AppSidebar.tsx`) filtrada por permiso.
6. Probar en claro y oscuro, y a ancho de celular.

## 5. Problemas conocidos (Windows)

| Síntoma | Causa y solución |
|---|---|
| `npm install` falla con `ERR_INVALID_ARG_TYPE` | Correr npm desde **PowerShell**, no desde Git Bash. |
| La API no conecta a la base | PostgreSQL no está encendido, o los datos `DB_*` de `api/.env` no coinciden con tu instalación. |
| El puerto 8000 está ocupado | Usamos **8010** para la API (y `API_URL=http://localhost:8010/api/v1` en `web/.env.local`). |
| La API va lentísima (logins de 10 s o más) | Estás apuntando a una base remota. En desarrollo se usa la base local. |
| Los tests borran datos que estaba usando | No pasa: Pest solo corre contra `zooetia_test` y se niega con otra base. |
| No llega el correo de invitación | En local los correos van al log: `api/storage/logs/laravel.log` (busca `invitacion/`). |
| Una tarea en cola no se ejecuta | Falta `php artisan queue:work` (la cola es la tabla `jobs`). |
| `next dev` reescribe `web/AGENTS.md` | Es esperado (Next 16); el archivo se commitea tal cual. |

## 6. Convenciones

- **Idioma:** la interfaz y los mensajes de error, en español de Colombia ("tutor", no "cliente"; "Atendida", no "Completed"). El código, los nombres y los comentarios, en inglés.
- **Fechas:** guardar en UTC, mostrar en la hora de la clínica. Nunca `new Date()` para "hoy" en el frontend: usar `todayIn(clinic.timezone)`.
- **Teléfonos:** E.164 en la base (`+57…`); `toE164` / `fromE164` / `formatPhone` en `web/src/lib/patients.ts`.
- **Dinero:** pesos colombianos sin decimales (`formatCop`).
- **Nada se borra** si tiene valor legal o histórico: se anula, se desactiva o se marca (`deleted_at`).
- **Permisos:** el frontend oculta, la API decide. Todo endpoint nuevo lleva su test de permiso.
- **Sin emojis** en la interfaz; íconos SVG.
