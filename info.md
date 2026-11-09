# SaaS para clínicas veterinarias (Colombia) — Brief del proyecto

> Este archivo es el contexto inicial para Claude Code. Léelo completo antes de escribir código. Donde diga **[PENDIENTE]**, no asumas: pregunta antes de decidir.

## 1. Qué estamos construyendo

Un SaaS multi-clínica para veterinarias en Colombia. Producto **enfocado en gestión veterinaria**, no un sistema contable completo. Lo impulsan 3 socios, y **Juan es el desarrollador principal**: el objetivo es un software **realmente funcional y sólido**, no un prototipo.

**Referencia principal de producto:** **OkVet (https://okvet.co/)**. Juan tiene una cuenta creada y hará el benchmark módulo por módulo; el MVP debe igualar su funcionalidad central, con diferenciadores puntuales (ver sección 3).

**Referencia secundaria:** Clinivet (somosclinivet.com). Está hecho con un generador tipo "vibe coding" y no es del todo funcional, así que solo sirve como comparación de ideas, **no** como modelo de calidad ni de alcance.

**Idioma:** UI, textos y datos de negocio en español (Colombia). Código, nombres de tablas y variables en inglés.

## 2. Alcance del MVP

### Incluido
- **Agenda / citas** con estados (programada, confirmada, atendida, cancelada, no asistió).
- **Tutores (dueños) y mascotas**: fichas con datos básicos (especie, raza, sexo, fecha de nacimiento, peso, etc.).
- **Historia clínica**: consultas, diagnósticos, tratamientos, vacunas, archivos adjuntos.
- **Recordatorios**: manuales (clic para abrir WhatsApp) en el plan básico; **automáticos por WhatsApp Business API** en el plan Pro.
- **Reserva online**: link público para que el tutor agende.
- **Inventario básico**: productos, stock, movimientos.
- **Registro simple de ingresos** (ventas/pagos de la clínica).
- **Multi-sede y roles** (admin, veterinario, recepción).
- **Facturación electrónica DIAN** como plan/add-on separado (ver sección 5).

### Fuera de alcance (decisión tomada, no construir)
- Contabilidad completa (PUC, libros contables, estados financieros, declaraciones tributarias). Las clínicas usan su contador / Siigo / Alegra. Solo se ofrecerá **exportar datos de ventas (CSV/API)**.
- App **móvil** para dueños de mascotas. (Un **portal web del tutor** con carnet QR sí está propuesto; ver §6b.)
- Marketplace.
- Integraciones con laboratorios.

## 3. Diferenciadores frente a la competencia

Competidores colombianos: GVET, OkVet, Ponkis, PawZone, Recorvet, QVET, VeteSoft, MyVete, además de Clinivet. Rango de mercado: **$70.000–$300.000 COP/mes** según módulos (la mayoría esconde precios detrás de "cotizar").

Huecos que queremos cubrir (contrastados con OkVet el 2026-09-29, ver [`docs/benchmark-okvet.md`](docs/benchmark-okvet.md)):
1. **Bot de WhatsApp automático nativo** ✅ confirmado: OkVet solo vende paquetes de mensajes salientes (50/mes en Pro); no tiene bot ni confirmación conversacional. Clinivet solo tiene recordatorio manual por clic y AgendaPro cobra WhatsApp como add-on.
2. **Facturación electrónica DIAN** integrada: ⚠️ OkVet **sí la tiene** (en Pro, $132.999). Solo es diferenciador frente a Clinivet; para nosotros es una capacidad necesaria.
3. **Multi-sede y roles** bien resueltos ✅ confirmado: en OkVet cada sede es otra cuenta "vinculada".
4. **Privacidad de datos:** tutores aislados por clínica y consentimiento por canal (OkVet comparte un registro global de tutores entre clínicas y activa SMS/WhatsApp de forma automática).
5. Reportes financieros y comisiones (post-MVP).

Atención: OkVet tiene **plan gratis** e **IA integrada** (resumen de HC, corrección de textos).

## 4. Stack técnico (decidido)

| Capa | Tecnología |
|---|---|
| Backend | **Laravel, API-only** |
| Auth | Laravel Sanctum (tokens) |
| Roles/permisos | Spatie Laravel-Permission |
| Multi-tenancy | **Una sola BD**, columna `clinic_id` + **Eloquent global scopes** (NO base de datos por tenant) |
| Colas/jobs | Laravel Horizon + Redis (recordatorios WhatsApp, facturación) |
| Frontend | **Una sola app Next.js** (decidido; base visual TailAdmin Free MIT, verde bosque + coral, Plus Jakarta Sans — ver [`docs/design.md`](docs/design.md)): rutas públicas con SSR para SEO (`/`, `/precios`, `/reservar/[clinica]`) + dashboard privado (`/app/...`). Consume la API de Laravel; no tiene BD propia |
| Móvil | React Native / Expo, consumiendo la misma API vía Sanctum (**[PENDIENTE]** confirmar si es para personal de la clínica y en qué fase; no es parte del arranque) |
| Base de datos | **PostgreSQL** (decidido). Local en cada computador para desarrollo y pruebas; un VPS para staging/producción |

**Infra de producción (existente):** VPS Hetzner. PHP-FPM para Laravel, PM2 para Next.js, Supervisor para workers de cola, Redis, Nginx. Costo estimado ~US$7–15/mes; suficiente para cientos de clínicas concurrentes.

**Por qué Laravel API-only y no Next.js full-stack:** mejor soporte para multi-tenancy y colas asíncronas, y una sola API sirve al dashboard web y a una futura app móvil.

## 5. Facturación electrónica (DIAN)

- Es una **capa de cumplimiento**, no contabilidad: emisión de factura electrónica (XML, CUFE, etc.).
- Proveedor previsto: **Factus** (alternativa: Alegra). **[PENDIENTE]** confirmar precios y crear cuenta sandbox.
- **Diseñar detrás de una interfaz** (`InvoicingProvider` o similar) para poder cambiar de proveedor sin tocar el dominio.
- Toda emisión va por **jobs en cola** con reintentos e idempotencia.

## 6. WhatsApp

- Usar **WhatsApp Business Cloud API** (Meta).
- Costo en Colombia: utility/servicio ≈ US$0.0008/msg, marketing ≈ US$0.0125/msg (prácticamente despreciable).
- Desde el **1 de octubre de 2026** Meta empieza a cobrar también los mensajes de servicio (antes gratis dentro de la ventana de 24 h). Modelar el costo por mensaje en el sistema.
- Envío siempre por cola; registrar cada mensaje (estado, plantilla, clínica) para auditoría.
- **Número compartido de Zooetia para todas las clínicas (decidido 2026-09-30).** Que cada clínica tramite su propia cuenta de WhatsApp API sería muy engorroso. Las plantillas nombran a la clínica y las respuestas se enrutan por mensaje citado, botón o último envío (detalle en `docs/database.md` §10). En el MVP solo se usan plantillas *utility*, para cuidar la reputación compartida del número.
- **Bot del MVP con botones de respuesta rápida** (Confirmar / Reprogramar / Cancelar), **sin IA**.

### 6a. IA (decidido 2026-09-30: post-MVP)
- No entra en el MVP. Antes hay que **medir el consumo de tokens**, que podría encarecer el plan. La base de datos ya lo contempla (`clinical_records.ai_assisted`, métrica `ai_tokens` en `usage_counters`).
- Candidatos, en orden: (1) bot de WhatsApp que entienda texto libre; (2) **dictado → historia clínica SOIP, cuando exista la app móvil**; (3) resumen de la historia clínica. Descartado: sugerir diagnósticos.

### 6b. Portal del tutor y carnet digital (decidido 2026-09-30: Fase 4, después de la reserva online)
- El tutor entra con su celular y un **código por WhatsApp** (sin contraseña) y ve sus mascotas en todas las clínicas Zooetia donde está registrado: foto, datos, **historial de vacunas**, próximas citas y controles, y lo que la clínica decida compartir.
- **Carnet/escarapela PDF con QR**: el QR abre una página pública mínima que **verifica el estado de vacunas** (útil para guarderías, peluquerías, viajes y conjuntos) y un **modo "mascota perdida"** con el contacto del tutor.
- **Carné en la app (decidido 2026-10-02, a futuro):** la app móvil del tutor mostrará el carné **con el mismo diseño** que el impreso (plantillas Clásico, Moderno y Huellitas, color elegido por la clínica) y su QR, para mostrarlo fácil desde el celular. La API ya entrega todo como datos (`GET /pets/{id}/card`), así que la app solo redibuja las plantillas de `web/src/components/pet-card/`. Antes llega en el portal web del tutor (Fase 4e). Idea adicional: pase de **Apple Wallet / Google Wallet**.
- Diseño de datos en `docs/database.md` §10b.
- El equipo ya tiene experiencia previa con un SaaS de confirmaciones por WhatsApp.

## 7. Planes y precios (propuesta, COP/mes)

| Plan | Precio | Incluye |
|---|---|---|
| **Básico** | $49.000–$69.000 | Agenda, historia clínica, fichas, recordatorios manuales |
| **Pro** | $89.000–$119.000 | Básico + bot WhatsApp automático + reserva online |
| **Pro + Facturación** | $129.000–$169.000 (o costo Factus + ~$40.000–$50.000) | Pro + facturación electrónica DIAN |

> **Antes de fijar los planes, leer [`docs/planes.md`](docs/planes.md):** la bandeja de WhatsApp (chat del equipo con el tutor) va en un plan superior, y ahí están los costos reales de Meta y lo que falta decidir.

Los planes deben poder activarse/desactivarse por **feature flags a nivel de clínica** (columna `plan` o tabla de suscripciones + gates en backend y frontend). Existe un simulador de ingresos publicado: https://claude.ai/artifact/SFj7Zpt7uXetYXTNKAHPUa

**Prueba gratis (decidido 2026-09-30):** no habrá plan gratis permanente (a diferencia de OkVet); cada clínica nueva arranca con una **prueba gratis** con funciones de Pro (**15 días**, decidido). Al vencer pasa a solo lectura hasta que elija un plan.

## 8. Modelo de datos

> **Fuente de verdad: [`docs/database.md`](docs/database.md)** (tablas, columnas, relaciones, índices, restricciones y decisiones). Esta sección solo lo resume. **[PENDIENTE]** validación final de Juan (ver §14 de ese documento).

Reglas principales:
- PostgreSQL, IDs **UUIDv7**, fechas en UTC (`timestamptz`).
- **Toda tabla de negocio lleva `clinic_id`**, con global scope y además **FKs compuestas `(clinic_id, id)`** para que la BD rechace referencias cruzadas entre clínicas.
- La historia clínica **no se borra**: se anula y se bloquea tras la ventana de edición. Hay auditoría con `activity_log`.
- Unidades normalizadas (kg, °C), dinero en `numeric(14,2)` y snapshots en ventas, facturas y fórmulas.

Dominios (detalle en el documento):
- **Plataforma:** `plans`, `subscriptions` (prueba gratis).
- **Clínica:** `clinics`, `branches` (multi-sede real), `users` + `clinic_user` + `branch_user` (un usuario puede estar en varias clínicas), roles con Spatie *teams*, `invitations`, perfil fiscal y resoluciones DIAN.
- **Tutores y mascotas:** `owners`, `owner_consents` (Habeas Data, opt-in por canal), `pets`, `pet_alerts` (alergias, crónicas, infecciosas), `pet_lifestyle`.
- **Catálogos** globales y de la clínica: especies, razas, vacunas, procedimientos, alertas clínicas y `services` (servicios con duración y precio).
- **Agenda:** `schedules`, `schedule_blocks`, `appointments`, `appointment_user` (encargados), `appointment_status_changes`.
- **Historia clínica:** tabla padre `clinical_records` + detalles 1:1 (`consultations` SOIP, `vaccinations`, `dewormings`, `prescriptions`, `procedures`, `follow_ups`, `clinical_notes`), más `vital_signs`, `physical_exam_findings`, `consultation_diagnoses` y `attachments`.
- **Operación:** `tasks`, `products`, `inventory_stocks`, `inventory_movements`, `sales`, `sale_items`, `payments` (registro simple).
- **FE DIAN:** `invoices` (idempotente, detrás de `InvoicingProvider`).
- **Mensajería:** `message_templates`, `reminder_rules`, `reminders`, `messages`, `whatsapp_accounts`, `whatsapp_conversations` (bot), `usage_counters`.

## 9. Convenciones de arquitectura

- **Aislamiento de tenant obligatorio:** trait `BelongsToClinic` con global scope y asignación automática de `clinic_id` al crear. Toda consulta cruzada entre clínicas es un bug de seguridad. Escribir **tests que verifiquen el aislamiento** desde el inicio.
- API REST versionada (`/api/v1`), respuestas JSON consistentes, Form Requests para validación, API Resources para serialización, Policies para autorización.
- Lógica de negocio en Actions/Services, controladores delgados.
- Jobs idempotentes y con reintentos para WhatsApp e invoicing.
- Migraciones + seeders con datos de ejemplo (una clínica demo con tutores, mascotas y citas).
- Tests con **Pest** (decidido).
- Zona horaria de negocio: `America/Bogota`; guardar fechas en UTC.

## 10. Estructura de repos

**Monorepo** (decidido) con `/api` (Laravel), `/web` (única app Next.js: marketing + reserva online + dashboard) y `/mobile` (Expo, más adelante).

## 11. Entorno de desarrollo local

- Desarrollo local **sin Docker** (decidido): PHP 8.4 y PostgreSQL 14+ instalados en cada computador, con una base para desarrollo y otra para pruebas. Sin Redis en local: colas y caché con driver `database`; Redis + Horizon solo en producción (Horizon no corre en Windows). Instalación paso a paso en el [`README.md`](README.md).
- Variables sensibles en `.env` (nunca commitear): credenciales de Meta/WhatsApp, Factus, BD, Redis.

## 12. Por dónde empezar (sugerencia de fases)

> **Avance (2026-10-06):** Fases 1 a 4 ✅ (falta conectar Meta), registro y reserva en línea ✅ (falta el cobro en línea) y **Fase 5a ✅** (inventario, ventas y reportes); pendiente la 5b (factura DIAN). Fase 4: recordatorios manuales y automáticos, bot de WhatsApp con botones y bandeja, carné con QR (diseñador, página pública, modo perdida), portal del tutor (acceso por invitación, sin códigos por ingreso) y pantalla de Tareas. **Todo lo que hace hoy la aplicación:** [`docs/funcionalidades.md`](docs/funcionalidades.md). **Cómo se llegó aquí:** [`docs/historial.md`](docs/historial.md). Siguen: reserva online y la Fase 5 (inventario, ingresos, facturación).

**Fase 1 — Cimientos**
1. Scaffolding de Laravel API + PostgreSQL + Redis en local.
2. Auth con Sanctum, modelo `clinics`/`users`, roles con Spatie.
3. Multi-tenancy (`BelongsToClinic`, global scope) **con tests de aislamiento**.

**Fase 2 — Núcleo clínico**
4. CRUD de `owners` y `pets`.
5. Agenda (`appointments`) con estados.
6. Historia clínica (`consultations`, vacunas, tratamientos, adjuntos).

**Fase 3 — Frontend**
7. Dashboard Next.js: login, agenda, fichas, historia clínica.

**Fase 4 — Diferenciadores**
8. Recordatorios manuales → bot de WhatsApp por cola.
9. Reserva online pública.
9b. Portal del tutor y carnet con QR verificable. *(Hecho en la 4e: el acceso quedó por **invitación de la clínica**, no por código de WhatsApp en cada ingreso; ver `docs/decisiones.md`.)*
10. Inventario e ingresos.
11. Facturación electrónica (interfaz de proveedor + Factus).

## 13. Pendientes generales

- Confirmar precios Factus/Alegra para cerrar el precio del plan con facturación.
- Validar el esquema de datos (sección 8).
- Definir qué rol tienen los otros 2 socios (producto, ventas, soporte a clínicas, pruebas, etc.), ya que Juan es el desarrollador principal.
- ~~Benchmark detallado de **OkVet**~~ Hecho el 2026-09-29: [`docs/benchmark-okvet.md`](docs/benchmark-okvet.md). Decidido: **prueba gratis** (ver §7).
- Definir nombre del producto (se usa "Zooetia" provisionalmente). Logo y colores definitivos: pendientes; la paleta actual (verde bosque + coral) se cambia en un solo archivo.

## 14. Cómo quiero que trabajes

- Trabajas con Juan, que es el desarrollador principal y toma las decisiones finales. Prioriza código funcional, probado y mantenible.
- Explica brevemente el **porqué** de las decisiones técnicas, no solo el qué.
- Avanza en pasos pequeños y verificables; ejecuta y prueba lo que escribes.
- Si algo del brief es ambiguo o está marcado como **[PENDIENTE]**, pregunta antes de asumir.
- Antes de generar código, propón el plan de la fase y espera confirmación.