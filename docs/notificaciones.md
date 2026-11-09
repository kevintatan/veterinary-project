# Notificaciones del equipo (la campana)

## Cómo funciona

- **Campana** en la barra superior, junto al modo oscuro, con el número de no leídas (9+ si son más).
- **No hay que refrescar:**
  - Se consulta sola **cada 20 segundos mientras la pestaña está visible** (se pausa si está oculta, para no gastar) y **al instante al volver** a la pestaña.
  - Cuando llega algo nuevo: **aviso emergente** abajo a la derecha (8 segundos, clic para ir), **sonido suave** de dos notas, y el número en el **título de la pestaña**: "(2) Agenda · Zooetia".
  - **Avisos de escritorio** (opcional, por persona): en la campana, "Avisarme en el escritorio aunque esté en otra ventana". El navegador pide permiso una vez; luego, si Zooetia no está a la vista, sale la notificación de Windows y al hacer clic abre Zooetia en el lugar indicado.
  - Los contadores relacionados (solicitudes en línea, agenda) se refrescan con el mismo evento.
- Al abrirla: las últimas 30, las no leídas resaltadas, cuánto hace ("hace 5 min", "ayer") y **"Marcar todas como leídas"**.
- **Clic en una notificación** la marca leída y lleva a donde corresponde (p. ej. Agenda → Solicitudes).
- Cada persona tiene **sus propias** notificaciones y su estado de leído, por clínica (si trabaja en dos clínicas, ve las de la clínica abierta).
- Funciona aunque la cuenta esté en modo consulta.

## Qué notifica hoy

| Tipo | Cuándo | A quién |
|---|---|---|
| `booking_request` 📅 | Llega una solicitud de cita en línea | Quienes gestionan citas (permiso `appointments.manage`) |
| `whatsapp_reply` 💬 | Un tutor cancela o reprograma una cita por WhatsApp, o retira su solicitud en línea | Quienes gestionan citas |

## Próximas (ya previstas en el diseño)

| Tipo | Cuándo |
|---|---|
| `task_assigned` ✅ | Te asignan una tarea |
| `document_signed` ✍️ | Un tutor firma un documento a distancia o desde el portal |

## Decidido para el servidor: tiempo real con WebSockets

> Decidido el 2026-10-03: en el servidor no se consulta "petición tras petición"; se usan sockets.

Al montar el servidor de producción (VPS) se pasa a **avisos instantáneos con WebSockets** (Laravel Reverb): el servidor empuja la notificación en el momento, en vez de que cada pantalla abierta pregunte cada 20 s. Plan:

1. Instalar **Laravel Reverb** (servidor de WebSockets propio de Laravel, sin servicios externos) como un proceso más, junto al trabajador de la cola, detrás de Nginx con HTTPS.
2. `NotifyStaff` emite un evento **por persona y clínica** (canal privado `clinic.{id}.user.{id}`), autorizado con la sesión.
3. La campana se suscribe con **Laravel Echo**; la consulta de 20 s queda como respaldo lento (p. ej. cada 5 minutos) por si el socket se cae.
4. El mismo canal puede avisar a la agenda (citas nuevas o movidas) para que se actualice sola.

La API y la campana no cambian: solo cambia **cómo** se entera la pantalla.

## Para programadores

```php
// A todos los que tienen un permiso (dentro del contexto de la clínica):
app(NotifyStaff::class)->withPermission(Permission::AppointmentsManage, 'booking_request', 'Título', 'Detalle', '/app/ruta');
// A una persona:
app(NotifyStaff::class)->user($userId, 'task_assigned', 'Título', 'Detalle', '/app/tareas');
```

| Pieza | Dónde |
|---|---|
| Tabla | `staff_notifications` (una fila por persona, con `read_at`) |
| Envío | `Notifications/Staff/NotifyStaff` |
| API | `NotificationController`: `GET notifications`, `POST notifications/{id}/read`, `POST notifications/read-all` |
| Web | `components/header/NotificationBell.tsx` (íconos por tipo en `ICON`) |
