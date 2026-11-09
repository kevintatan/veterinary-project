# Documentación de Zooetia — empieza aquí

Zooetia es un SaaS para clínicas veterinarias en Colombia: agenda, historia clínica, tutores y mascotas, recordatorios por WhatsApp y portal del tutor. Varias clínicas comparten la misma instalación sin ver nunca los datos de las otras (*multi-tenant*).

## Qué leer según lo que vas a hacer

| Si vas a… | Lee, en este orden |
|---|---|
| **Conocer todo lo que hace Zooetia** (presentarlo al equipo) | [`funcionalidades.md`](funcionalidades.md) (catálogo completo, por módulo) → [`historial.md`](historial.md) (qué se construyó cada día, pedidos y correcciones) |
| **Entender el producto** | [`../info.md`](../info.md) (visión, decisiones de negocio, planes) → [`modulos.md`](modulos.md) (qué hace cada pantalla y sus reglas) |
| **Programar por primera vez** | [`../README.md`](../README.md) (montar el entorno local) → [`desarrollo.md`](desarrollo.md) (flujo de trabajo, recetas, problemas comunes) → [`arquitectura.md`](arquitectura.md) |
| **Tocar la base de datos** | [`database.md`](database.md) (cada tabla, por qué existe y sus reglas) |
| **Diseñar o maquetar** | [`design.md`](design.md) (colores, tipografía, componentes, reglas de gráficos) |
| **Entender por qué algo es así** | [`decisiones.md`](decisiones.md) (registro de decisiones con fecha y motivo) |
| **Trabajar en WhatsApp, recordatorios o portal** | [`fase-4.md`](fase-4.md) (plan y estado) → [`whatsapp.md`](whatsapp.md) (cómo funciona y cómo conectar Meta) |
| **Trabajar en el portal del tutor** | [`portal.md`](portal.md) (acceso por invitación, seguridad, dónde está el código) |
| **Trabajar en consentimientos y firmas** | [`documentos.md`](documentos.md) (tipos, cómo se firma, evidencias legales, código) |
| **Inventario e ingresos (Fase 5a)** | [`inventario.md`](inventario.md) (productos, existencias, movimientos, alertas, importación, decisiones) |
| **Reserva de citas en línea** | [`reservas.md`](reservas.md) (página pública, solicitudes, aprobación) |
| **Notificaciones (campana)** | [`notificaciones.md`](notificaciones.md) |
| **Registro de clínicas, estados del plan y cobro** | [`cobro.md`](cobro.md) (registro, prueba, gracia, activación manual, plan de Mercado Pago) |
| **Definir planes, precios o límites** | [`planes.md`](planes.md) (qué incluye cada plan, costos de Meta y decisiones pendientes) |
| **Comparar con la competencia** | [`benchmark-okvet.md`](benchmark-okvet.md) |

## Estado (2026-10-06)

| Fase | Contenido | Estado |
|---|---|---|
| 1. Fundamentos | Multi-clínica, usuarios, roles, suscripción y prueba gratis | ✅ |
| 2. Núcleo clínico (API) | Catálogos, tutores y mascotas, agenda, historia clínica, tareas | ✅ |
| 3. Dashboard web | Inicio con gráficos, agenda, fichas, historia clínica con PDF, configuración | ✅ |
| 4. Diferenciadores | Recordatorios, bot y bandeja de WhatsApp, carné con QR, portal del tutor, tareas | ✅ (falta solo conectar Meta: ver [`fase-4.md`](fase-4.md)) |
| Registro y reserva en línea | Registro de clínicas, plan y facturación (activación manual), landing, reserva de citas en línea, campana de notificaciones | ✅ (falta el cobro en línea: ver [`cobro.md`](cobro.md)) |
| 5a. Inventario y ventas | Inventario por sede, ventas y pagos, cobro desde la cita, reportes y exportación a Excel | ✅ |
| 5b. Facturación electrónica DIAN | Factura electrónica, resolución y numeración | Pendiente (con un contador) |

## Glosario rápido

| Término | Significado |
|---|---|
| **Clínica** (*tenant*) | Cada cliente de Zooetia. Todo dato de negocio lleva `clinic_id`. |
| **Sede** (*branch*) | Una clínica puede tener varias sedes; la agenda y los horarios son por sede. |
| **Tutor** (*owner*) | El dueño de la mascota. Se dice "tutor", no "cliente". Es **por clínica**: el mismo humano en dos clínicas son dos registros. |
| **Miembro** | Un usuario (persona) dentro de una clínica, con un rol. Un usuario puede ser miembro de varias clínicas. |
| **Registro clínico** | Una entrada de la historia: consulta, vacuna, desparasitación, fórmula, procedimiento, seguimiento o nota. |
| **Ventana de edición** | Tiempo (24 h por defecto) en que el autor puede corregir un registro clínico; después queda cerrado. |
| **Anular** | Los registros clínicos nunca se borran: se anulan con motivo y quedan tachados. |
| **BFF** | *Backend for frontend*: Next.js guarda el token en una cookie y hace de puente hacia Laravel. El navegador nunca ve el token. |
| **WABA** | *WhatsApp Business Account* de Meta. Necesaria para el WhatsApp automático (Fase 4). |
