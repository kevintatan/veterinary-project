# Reserva de citas en línea

> Estado al 2026-10-03: **B1, B2 y B3 listos**: configuración, página pública, solicitudes con aprobación de la clínica, WhatsApp al tutor, campana en vivo, pedir cita desde el portal, QR y cartel para el mostrador.

## Decisión clave: la clínica siempre aprueba (2026-10-03)

Aunque la agenda esté abierta, puede no haber tiempo real para atender. Por eso una reserva en línea **no es una cita**: es una **solicitud** (estado `requested`, "Solicitud en línea") que **aparta la hora** (nadie más puede pedirla) hasta que el equipo la **acepta** (pasa a "Programada" y sigue el flujo normal: recordatorio y confirmación por WhatsApp) o la **rechaza** con un motivo (pasa a "Cancelada" y la hora se libera).

## Otras decisiones (2026-10-03)

- **Se pide el documento** al reservar (tipo y número, obligatorios), además del WhatsApp: identifica al tutor de forma más fiable que el celular. Tipos permitidos: cédula de ciudadanía, cédula de extranjería, PPT, pasaporte y documento extranjero. Se guarda sin puntos ni espacios (`1.095.812.233` → `1095812233`).
- **Las solicitudes sin respuesta se quedan pendientes** (no se vencen solas). Si la hora pasa, la pantalla de solicitudes dice "La hora ya pasó: recházala".

## Para el tutor: `/reservar/{clínica}`

Página pensada para el celular, con el logo, dirección y teléfono de la clínica y su mensaje (p. ej. "Para urgencias llama al…").

1. **¿Qué necesitas?** Sede (si hay varias) y servicio, con duración y precio.
2. **¿Cuándo?** Profesional (opcional, si la clínica lo permite; si no, "cualquiera disponible"), día (tira de 14 días + "otra fecha") y hora, agrupadas en mañana y tarde. Solo se ven horas **realmente libres**: horarios, bloqueos, citas y otras solicitudes.
3. **Tus datos:** nombre, celular (WhatsApp), **tipo y número de documento**, nombre y especie de la mascota, nota opcional, y **dos autorizaciones obligatorias**: tratamiento de datos (Ley 1581) y que la clínica le escriba por WhatsApp sobre la cita.
4. **"¡Solicitud enviada!"** con el resumen y el aviso claro: **"Tu cita aún no está confirmada."**

**Privacidad:** la página **nunca** dice si un celular ya es cliente ni muestra mascotas de nadie (cualquiera podría escribir el celular de otra persona). Nada se crea en la base de tutores hasta que la clínica acepta.

**Anti-abuso:** máximo **2 solicitudes pendientes por celular o documento** en una clínica; límites por conexión (8 envíos por minuto, 40 al día; 60 consultas de horas por minuto); la hora se vuelve a verificar al enviar (si alguien la tomó, se pide elegir otra).

## Para la clínica

**Configuración → Reserva en línea** (administradores; incluido desde el plan **Pro**, en Básico se muestra "disponible desde Pro"):
- Activar/desactivar y **el enlace para compartir** (copiar / abrir).
- Anticipación mínima (0 a 48 h), hasta cuántos días adelante (1 a 90), dejar elegir profesional, mensaje en la página.
- Qué servicios aparecen: los marcados con "Los tutores pueden pedirlo en línea" en Servicios.

**Agenda:**
- La solicitud aparece **punteada en violeta** ("Toby · solicitud"); al abrirla, un recuadro con quién la pidió y el botón "Aceptar o rechazar".
- Botón **"Solicitudes en línea (N)"** arriba de la agenda e insignia con el número en el menú lateral (Agenda).

**Agenda → Solicitudes** (`/app/agenda/solicitudes`, quien gestiona citas):
- Cada solicitud: día y hora, servicio, profesional asignado (y si lo eligió el tutor), sede, mascota, especie, nombre, celular (enlace a WhatsApp), nota y cuándo la pidió.
- **Al aceptar**, Zooetia **sugiere** el tutor si coincide el **documento** (la coincidencia más fuerte) o el **celular**, y dice por qué ("mismo documento", "mismo celular" o ambos); marca la mascota con el mismo nombre. Se puede vincular a esa mascota, a una mascota nueva de ese tutor, o crear tutor y mascota nuevos.
  - **Si el documento ya existe, no se puede crear un tutor nuevo** (sería un duplicado): la opción no aparece y la API también lo impide.
  - **Mismo documento, otro celular** (la persona cambió de número): se avisa "celular registrado distinto" y se ofrece **"Actualizar su celular"** (marcado por defecto), porque los recordatorios llegan a ese número.
  - Si el tutor encontrado por celular no tenía documento, **se le completa** con el de la reserva.
  - Se registran sus autorizaciones (fuente "reserva en línea", con evidencia).
- **Al rechazar**, motivo obligatorio con atajos ("No tenemos disponibilidad a esa hora", "Ese servicio requiere una valoración previa", "Te contactaremos para acordar otra hora"). En B2 ese motivo le llegará al tutor por WhatsApp.
- "Cualquiera disponible" se asigna al profesional con **menos citas ese día** (siempre se puede cambiar después en la cita).

## WhatsApp al tutor (B2)

| Momento | Plantilla | Botones |
|---|---|---|
| Envía la solicitud | `zooetia_solicitud_recibida`: "recibimos tu solicitud… *Tu cita aún no está confirmada.*" | **Cancelar solicitud** |
| La clínica acepta | `zooetia_solicitud_aceptada`: "aceptó tu solicitud… te enviaremos un recordatorio el día anterior" | Reprogramar · Cancelar (el bot de siempre) |
| La clínica rechaza | `zooetia_solicitud_rechazada`: "no pudo aceptar… Motivo: … Puedes pedir otra hora aquí: (enlace de reservas)" | — |

- Son **plantillas** (Meta exige plantilla cuando escribimos primero); textos en [`whatsapp.md`](whatsapp.md) §3, pendientes de aprobar junto con las demás.
- Se envían **a cualquier hora** (responden a algo que el tutor acaba de hacer) y **no los frena el límite mensual** del plan, aunque **sí se cuentan** en él.
- **Antes de aceptar** se escribe al celular del formulario; **después**, al del tutor (que pudo actualizarse al aceptar).
- Si la situación cambió antes de enviar (p. ej. la aceptaron un segundo después de recibirla), el mensaje viejo no sale.
- **"Cancelar solicitud"** desde WhatsApp: el bot pregunta "¿Seguro?" (Sí, cancelar / No, la mantengo); al cancelar, la hora se libera y la clínica recibe aviso en la campana. Cualquier otro botón sobre una solicitud responde "aún está en revisión".
- Trabajo en segundo plano: `Jobs/SendBookingMessage` (reintenta 2 veces si Meta falla temporalmente). **En desarrollo hay que tener corriendo el trabajador de la cola** (`php artisan queue:work`), si no los mensajes quedan esperando.

## Notificaciones

Cada solicitud nueva llega a la **campana** de las personas que gestionan citas, **sin refrescar** (aviso emergente, sonido y número en la pestaña); también cuando un tutor cancela o reprograma por WhatsApp. Ver [`notificaciones.md`](notificaciones.md).

## Código

| Pieza | Dónde |
|---|---|
| Estado `requested` | `Enums/AppointmentStatus` (solo puede pasar a cancelada por el flujo normal; aceptar es una acción propia) |
| Reglas, horas y creación | `Booking/OnlineBooking` (config, `slots`, `request`, `leastBusy`) |
| Aceptar / rechazar / sugerencias | `Booking/BookingRequests` |
| API pública | `PublicBookingController` (`GET/POST public/booking/{slug}`, `slots`, `logo`) |
| API de la clínica | `BookingRequestController` (`booking-requests`, `count`, `accept`, `reject`, `online-booking/settings`) |
| Ajustes | `clinics.settings.online_booking` (valores por defecto en `Clinic::DEFAULT_SETTINGS`) |
| Datos de la solicitud | `appointments.booking_details` (jsonb) |
| Web pública | `app/reservar/[slug]` (`BookingFlow.tsx`), BFF `app/api/public/booking/[slug]` |
| Web clínica | `configuracion/sections/OnlineBookingSection.tsx`, `agenda/solicitudes/RequestsView.tsx`, `lib/booking.ts` |
| Pruebas | `tests/Feature/Agenda/OnlineBookingTest.php` (11) |

## Desde el portal del tutor (B3)

- En el inicio del portal, botón **"Pedir una cita"**; en el carné de cada mascota, **"Pedir una cita para Rocky"** (con la mascota ya elegida). Solo aparecen si la clínica tiene la reserva en línea activa.
- Es **el mismo formulario** de la página pública, con nombre, celular y documento **ya llenos** y las mascotas del tutor para elegir (o "Otra mascota"). Las autorizaciones se marcan igual, de forma explícita.
- Sigue siendo una **solicitud**: la clínica la ve con la coincidencia "mismo documento y celular" y la mascota sugerida.
- Si el tutor tiene mascotas en varias clínicas, primero elige la clínica.
- API: `GET portal/booking` (`PortalBookingController`); web: `app/portal/agendar`.

## QR y cartel para el mostrador (B3)

- **Configuración → Reserva en línea** muestra el **código QR** del enlace, con **Descargar QR** (PNG) e **Imprimir cartel**.
- El **cartel** (`/imprimir/reservas`, tamaño carta): logo y nombre de la clínica, "Pide tu cita desde tu celular", el QR grande, la dirección del enlace y tres pasos (escanea, elige, te confirmamos por WhatsApp). `?vista=1` lo muestra sin abrir la impresión.
- API: `GET online-booking/qr` (`OnlineBooking::qrPng`, misma librería del QR del carné).

## Landing y planes

La reserva en línea dejó de decir "próximamente" en la landing y en Plan y facturación (`Plan::FEATURE_LABELS`); la landing tiene su tarjeta "Reserva en línea" y una pregunta frecuente. La landing refresca el catálogo de planes cada 10 minutos.

## Ideas para después

- Notificaciones en **tiempo real con WebSockets** (Laravel Reverb) cuando estemos en el servidor, en vez de consultar cada 20 segundos ([`notificaciones.md`](notificaciones.md)).
- Que el tutor vea en el portal sus **solicitudes pendientes** (y pueda retirarlas desde ahí, además de WhatsApp).
