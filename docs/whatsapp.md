# WhatsApp y recordatorios

Cómo funcionan los recordatorios (manuales y automáticos), qué está listo y **cómo conectar la cuenta de WhatsApp de Meta (WABA)** cuando el trámite termine. Plan de la fase en [`fase-4.md`](fase-4.md).

## 1. Qué hay hoy

| Pieza | Estado |
|---|---|
| **Recordatorios manuales** (botón "Recordar" → abre WhatsApp de la clínica con el texto escrito) | ✅ Funcionando (4a) |
| **Recordatorios automáticos** (reglas, horario de envío, planificación, envío en segundo plano, reintentos, bitácora, consumo del plan) | ✅ Funcionando con el **conector simulado** (4c) |
| **Conector real de Meta** (`MetaWhatsAppProvider`, formato Cloud API, errores temporales vs. permanentes) | ✅ Escrito y probado con respuestas simuladas de Meta; **falta conectarlo** |
| **Bot de confirmación** (botones Confirmar / Reprogramar / Cancelar, webhook de Meta, bandeja WhatsApp para el equipo) | ✅ Funcionando con el **conector simulado** y el **simulador de tutor** (4d) |

Mientras `WHATSAPP_DRIVER=fake`, **ningún mensaje sale del servidor**: se registran en el log (`storage/logs/laravel.log`, línea `whatsapp.fake`) y en la tabla `messages`, y la pantalla muestra "simulado".

## 2. Cómo funciona

```
Cita creada / reprogramada / cancelada  ─┐
Vacuna o control registrado / anulado   ─┼─► ReminderPlanner ─► reminders (pendiente, con hora)
                                          │      (observers de Appointment y ClinicalRecord)
Cada minuto: reminders:dispatch ─► cola ─► SendReminder ─► vuelve a verificar todo
                                                         ─► WhatsAppProvider (fake | meta)
                                                         ─► messages (bitácora) + usage_counters
```

### Respuestas del tutor y bot (4d)

```
Meta ─► POST /api/v1/webhooks/whatsapp  (firma X-Hub-Signature-256 con el App secret; si no cuadra → 401)
     ─► cola: ProcessWhatsAppWebhook ─► WebhookProcessor
            ├─ estados (entregado / leído / falló) ─► actualiza messages
            └─ mensajes del tutor ─► ¿a qué clínica? ─► WhatsAppBot (dentro de esa clínica)
```

**¿A qué clínica va la respuesta?** (número compartido, en este orden):
1. El **botón** trae el id de nuestro mensaje (`confirm:{id}`): de ahí salen la clínica y la cita. Es el caso normal.
2. Si el tutor **citó** un mensaje (`context.id`), la clínica de ese mensaje.
3. La clínica que le escribió por última vez a ese número (7 días).
4. Una clínica que tenga un tutor con ese celular.
5. Si nada aplica, se guarda sin clínica (`messages.clinic_id` null) para revisión: nunca se pierde.

**Qué hace el bot** (`app/Messaging/Bot/WhatsAppBot.php`, sin IA):

| El tutor… | El bot… |
|---|---|
| toca **Confirmar** | confirma la cita (historial con canal `whatsapp`) y agradece |
| toca **Cancelar** | pregunta "¿Seguro?" (Sí, cancelar / No, la mantengo); si confirma, cancela con motivo "Cancelada por el tutor por WhatsApp" |
| toca **Reprogramar** | ofrece **3 espacios libres reales** (días distintos, mismo profesional y duración, con 2 h de anticipación mínimo); al elegir uno **vuelve a verificar** que siga libre y mueve la cita |
| escribe texto libre | **con `whatsapp_inbox` en el plan:** lo pasa a la **bandeja WhatsApp** del equipo (menú lateral, con contador) y responde una vez "te responderán pronto" (no repite el aviso en 2 h). **Sin ella:** responde una vez con el WhatsApp propio de la clínica (`wa.me/…`, teléfono de Configuración). Ver [`planes.md`](planes.md) |
| responde una cita ya cancelada o pasada | le dice que ya no está vigente |

Al mover o cancelar la cita, los recordatorios se replanifican solos (observers de 4c).

**Bandeja WhatsApp** (`/app/whatsapp`, solo planes con `whatsapp_inbox`): pestañas "Por responder" y "Todas", el chat con burbujas como en el celular, y respuesta del equipo. **Regla de Meta:** solo se puede escribir texto libre dentro de las **24 h** siguientes al último mensaje del tutor; después, solo plantillas. La pantalla lo indica y bloquea el envío. Las respuestas dentro de esa ventana no las cobra Meta (se cuentan en `usage_counters` como `whatsapp_service` para ver el volumen).

**Simulador (solo con `WHATSAPP_DRIVER=fake`):** para probar sin Meta.
- En la ficha del tutor → *Mensajes* → abrir un recordatorio automático → botones "Simular respuesta del tutor".
- En la bandeja: tocar los botones de cualquier mensaje o "Escribir como el tutor".
- Por debajo llama `POST /api/v1/dev/whatsapp/simulate`, que arma **el mismo JSON que manda Meta** y lo pasa por el mismo procesador. Con `meta` responde 404.

### Planificación (`app/Messaging/Reminders/ReminderPlanner.php`)
- **Reglas por clínica** (`reminder_rules`): por defecto *24 h antes de cada cita* y *7 días antes del próximo control* (vacuna, desparasitación o control). Se crean al registrar la clínica; se cambian en **Configuración → Recordatorios**.
- **Ventana de envío** (por defecto 8:00–19:00, hora de la clínica): lo que cae antes se mueve a la apertura; lo que cae después, al cierre del mismo día. Nunca se envía de noche.
- **Citas agendadas a última hora** (menos de 3 h por defecto): no se recuerdan.
- **Tarde pero a tiempo**: si la hora planeada ya pasó (p. ej. la cita se agendó hoy para mañana) y el evento está a 12 h o más, se envía en cuanto se pueda.
- **Reprogramar** mueve el recordatorio; **cancelar**, "no asistió" o "atendida" lo cancela. Un recordatorio ya enviado no se repite para el mismo evento; si la cita se mueve a otra fecha, se planea uno nuevo.
- Los controles cuentan desde las 9:00 del día del control.
- Cambiar reglas u horario **recalcula** todo lo pendiente (`replanClinic`). También existe `php artisan reminders:replan` (tras importar datos o restaurar un respaldo).

### Envío (`app/Jobs/SendReminder.php`)
Justo antes de enviar se vuelve a verificar todo, porque pudo cambiar desde que se planeó:

| Si… | Estado |
|---|---|
| la cita ya no está programada/confirmada o cambió de hora; el registro se anuló | `cancelled` |
| ya se aplicó una vacuna más nueva **de la misma vacuna** (o un registro más nuevo del mismo tipo) | `skipped_answered` |
| la mascota falleció | `skipped_deceased` |
| el plan no incluye automáticos (Básico) o la suscripción está vencida | `skipped_plan` |
| se alcanzó el límite mensual del plan (Pro: 500) | `skipped_limit` |
| el tutor no tiene celular | `skipped_no_phone` |
| el tutor no autorizó WhatsApp (Ley 1581) | `skipped_no_consent` |
| es fuera del horario de envío | vuelve a `pending` para la próxima apertura |

**Nunca dos veces:** la fila se bloquea y pasa `pending → queued → sending → sent` dentro de transacciones; el dispatcher marca `queued` en una sola sentencia. Si el proceso se cae justo durante el envío, el recordatorio queda en `sending` para revisión manual: se prefiere no enviar a enviar doble.

**Reintentos:** errores temporales (Meta caído, límite de velocidad) se reintentan a los 5 y 30 min; a los 3 intentos queda `failed`. Errores permanentes (número inválido, plantilla no aprobada) fallan de una vez. El error queda visible en Configuración → Recordatorios → "No enviados".

### Texto (`app/Messaging/ReminderTexts.php`)
Un solo lugar para el texto de todos los canales. Cada mensaje se arma con las **mismas variables** que recibe la plantilla aprobada en Meta, así el manual y el automático dicen lo mismo. Meta rechaza variables vacías: todas tienen siempre contenido.

## 3. Plantillas para aprobar en Meta

Categoría **Utility**, idioma **Español (Colombia) – `es_CO`**. El nombre y el cuerpo deben ser **exactamente** estos (el código envía las variables en este orden):

**`zooetia_recordatorio_cita`** (con botones)
```
Hola {{1}}, te escribimos de *{{2}}*. Te recordamos que *{{3}}* tiene cita {{4}}. ¿Nos confirmas tu asistencia? Usa los botones de abajo. ¡Gracias!
```
**Botones** (tipo *Respuesta rápida*, en este orden): `Confirmar` · `Reprogramar` · `Cancelar`. El código llena el *payload* de cada botón con `confirm:{id}`, `reschedule:{id}`, `cancel:{id}` (id de nuestro mensaje): así el bot sabe de qué cita y clínica se trata.

(El recordatorio **manual** desde el WhatsApp de la clínica sigue con el texto "Si no puedes asistir, por favor avísanos respondiendo este mensaje": allá no hay botones.)
Ejemplo para la revisión de Meta: `{{1}}` Luz · `{{2}}` Veterinaria Anipet (Sede Cabecera) · `{{3}}` Kira · `{{4}}` de consulta general a las 9:30 a. m. del martes 6 de octubre

**`zooetia_recordatorio_control`**
```
Hola {{1}}, te escribimos de *{{2}}*. A *{{3}}* le corresponde {{4}} el {{5}}. ¿Te ayudamos a agendar la cita? Responde este mensaje. ¡Gracias!
```
Ejemplo: `{{1}}` Luz · `{{2}}` Veterinaria Anipet · `{{3}}` Kira · `{{4}}` el refuerzo de la vacuna Rabia · `{{5}}` 8 de octubre

### Reserva en línea (docs/reservas.md, B2)

Mismas variables {{1}} a {{4}} en las tres: nombre del tutor · clínica (y sede si hay varias) · mascota · "servicio a las 9:00 a. m. del lunes 5 de octubre" (la fecha va al final para no terminar en "a. m.", que dejaría "a. m..").

**`zooetia_solicitud_recibida`** (con botón)
```
Hola {{1}}, recibimos tu solicitud de cita en *{{2}}* para *{{3}}*: {{4}}. La clínica la revisará y te responderá por aquí. *Tu cita aún no está confirmada.*
```
**Botón** (respuesta rápida): `Cancelar solicitud` → payload `cancel:{id}`.
Ejemplo: `{{1}}` Andrés · `{{2}}` Veterinaria Anipet · `{{3}}` Max · `{{4}}` desparasitación a las 11:00 a. m. del miércoles 7 de octubre

**`zooetia_solicitud_aceptada`** (con botones)
```
¡Hola {{1}}! *{{2}}* aceptó tu solicitud: *{{3}}* tiene cita {{4}}. Te enviaremos un recordatorio el día anterior. ¡Te esperamos!
```
**Botones**, en este orden: `Reprogramar` · `Cancelar` → payloads `reschedule:{id}`, `cancel:{id}`.

**`zooetia_solicitud_rechazada`**
```
Hola {{1}}, *{{2}}* no pudo aceptar tu solicitud de cita para *{{3}}* {{4}}. Motivo: {{5}}. Puedes pedir otra hora aquí: {{6}}
```
Ejemplo: `{{5}}` No tenemos disponibilidad a esa hora · `{{6}}` https://zooetia.co/reservar/anipet

Si Meta pide cambiar un texto: cambiar la constante en `ReminderTexts.php` **y** esta sección, y volver a enviar la plantilla. Los nombres pueden cambiarse sin tocar código con `WHATSAPP_TPL_APPOINTMENT`, `WHATSAPP_TPL_NEXT_CONTROL`, `WHATSAPP_TPL_BOOKING_RECEIVED`, `WHATSAPP_TPL_BOOKING_ACCEPTED` y `WHATSAPP_TPL_BOOKING_REJECTED`.

## 4. Conectar la WABA (paso a paso)

### A. Trámite en Meta (lo hace el equipo)
1. **Meta Business** (business.facebook.com): crear el portafolio de negocio a nombre de la empresa.
2. **Verificar la empresa** (Configuración del negocio → Centro de seguridad): pide RUT o certificado de Cámara de Comercio y un dominio o teléfono de la empresa. Puede tardar días.
3. **developers.facebook.com** → *Crear app* → tipo *Negocios* → agregar el producto **WhatsApp**. Esto crea una WABA de prueba con un **número de prueba** (envía solo a 5 números que registres): sirve para probar todo antes del número definitivo.
4. **Número definitivo**: una línea que **no** tenga cuenta en la app de WhatsApp (ni personal ni Business). Se registra en *WhatsApp → Configuración de la API → Agregar número*. Nombre visible: **Zooetia** (Meta lo revisa).
5. **Plantillas**: *WhatsApp Manager → Plantillas de mensajes* → crear las de la §3. Aprobación: minutos a horas.
6. **Token permanente**: *Configuración del negocio → Usuarios del sistema* → crear un usuario del sistema (administrador), asignarle la app y la WABA, y **generar un token** con los permisos `whatsapp_business_messaging` y `whatsapp_business_management`. (El token temporal del panel de la app vence en 24 h: no sirve para producción.)
7. Anotar: **Phone number ID**, **WhatsApp Business Account ID**, el **token** y el **App secret** (*Configuración de la app → Básica*).

### B. Configurar el servidor
En `api/.env` (nunca en el repositorio):

```env
WHATSAPP_DRIVER=meta
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_WABA_ID=...
WHATSAPP_ACCESS_TOKEN=...
WHATSAPP_APP_SECRET=...
WHATSAPP_VERIFY_TOKEN=una-cadena-larga-al-azar   # para el webhook (4d)
WHATSAPP_DISPLAY_PHONE=+57...
```

Luego `php artisan config:clear` (o `config:cache` en producción) y reiniciar los workers de la cola.

**Webhook** (en la app de Meta → *WhatsApp → Configuración*):
- URL de devolución: `https://<dominio-api>/api/v1/webhooks/whatsapp` (debe ser HTTPS público).
- Token de verificación: el mismo valor de `WHATSAPP_VERIFY_TOKEN`. Al guardar, Meta llama con `GET` y el servidor le devuelve el *challenge*.
- Suscribirse al campo **`messages`** (trae los mensajes del tutor y los estados entregado/leído).

### C. Probar
1. Con el número de prueba, registrar tu celular como destinatario en el panel de Meta.
2. Crear un tutor con ese celular y la autorización de WhatsApp, y una cita para mañana.
3. `php artisan reminders:dispatch` y `php artisan queue:work --stop-when-empty`.
4. Debe llegar el mensaje; en Configuración → Recordatorios → "Enviados" ya no dice "simulado". Si falla, el motivo aparece en "No enviados" (p. ej. `Meta 400: Template name does not exist`).
5. Tocar **Confirmar** en el celular: la cita debe quedar confirmada y llegar el "¡Gracias!". Escribir un texto: debe aparecer en la bandeja WhatsApp con el contador.

## 5. Operación

- **Servidor:** el scheduler de Laravel (`* * * * * php artisan schedule:run`) dispara `reminders:dispatch` cada minuto, y un worker de cola (Horizon con Redis en producción) envía.
- **Local (Windows):** `php artisan schedule:work` y `php artisan queue:work` en dos terminales, o a mano: `php artisan reminders:dispatch` + `php artisan queue:work --stop-when-empty`.
- **Costos de Meta y planes:** ver [`planes.md`](planes.md) (qué se paga, qué no, la ventana de 24 h y el caso de vacuna con cita ya agendada).
- **Consumo:** `usage_counters` (métrica `whatsapp_utility`) por clínica y mes. El plan define el límite (`plans.limits.whatsapp_messages`; Básico 0 = solo manuales; Pro 500).
- **Número compartido:** si una clínica abusa y baja la calidad del número en Meta, afecta a todas. Por eso solo plantillas *utility*, con tope por clínica.
