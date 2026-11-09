# Planes y costos (insumo para definir los planes comerciales)

Este documento **no** es la lista de precios final: junta lo que ya está decidido y lo que hay que tener en cuenta cuando se definan los planes. Los planes viven en la tabla `plans` (`database/seeders/PlansSeeder.php`): cada uno tiene `features` (qué incluye) y `limits` (cuánto). El código **siempre** pregunta por la *feature*, nunca por el nombre del plan, así que se pueden crear, renombrar o reorganizar planes sin tocar código.

```php
Subscription::allows('whatsapp_inbox')   // ¿el plan actual incluye esto y la clínica no está en solo lectura?
```

## 1. Funciones que dependen del plan (hoy)

| `features.*` | Qué habilita | Dónde se verifica |
|---|---|---|
| `whatsapp_bot` | **Recordatorios automáticos** por WhatsApp + **bot de botones** (Confirmar / Reprogramar / Cancelar) | `SendReminder` (si no, `skipped_plan`) |
| `whatsapp_inbox` | **Bandeja WhatsApp**: el equipo conversa con el tutor desde Zooetia | `ConversationController`, `WhatsAppBot`, menú lateral |
| `online_booking` | Reserva en línea | `OnlineBooking::isOpen` |
| `advanced_reports` | Reporte de ventas del periodo y exportación a Excel (el cierre del día es para todos) | `ReportController` |
| `invoicing` | Facturación electrónica DIAN (Fase 5b, sin construir; sale como "próximamente") | — |

| `limits.*` | Qué limita |
|---|---|
| `whatsapp_messages` | Recordatorios automáticos al mes (plantillas *utility*). `0` = solo manuales; `null` = sin límite |
| `branches`, `users` | Sedes y usuarios (`null` = sin límite) |
| `dian_documents` | Documentos electrónicos al mes |

**Sin plan que lo incluya, todo sigue siendo usable a mano:** el botón "Recordar" abre el WhatsApp propio de la clínica con el texto listo (gratis, sin Meta).

## 2. Decisión: la bandeja (chat con el equipo) va en un plan superior — 2026-10-02

**Por qué:** el costo de conversar no es tanto Meta como el **tiempo del equipo** (la clínica pasa a dar soporte por un canal más) y el posible cobro de las respuestas libres en Colombia (ver §3). Es una función de valor alto: se vende en el plan más caro.

**Cómo se comporta según el plan:**

| | Sin `whatsapp_bot` | `whatsapp_bot` sin `whatsapp_inbox` | `whatsapp_bot` + `whatsapp_inbox` |
|---|---|---|---|
| Recordatorios | Solo manuales (WhatsApp de la clínica) | Automáticos con botones | Automáticos con botones |
| Tutor toca un botón | — | El bot confirma / reprograma / cancela | Igual |
| Tutor escribe texto libre | — | **Una** respuesta: *"Por este número solo enviamos recordatorios. Para otras consultas escríbele directamente a {clínica}: wa.me/57…"* (teléfono de la clínica en Configuración) | Llega a la **bandeja** del equipo, con contador en el menú |
| Menú "WhatsApp" | Oculto | Oculto (si entran por URL: "Tu plan no incluye…") | Visible |

**Hoy, provisionalmente:** Básico = nada; Pro = `whatsapp_bot`; Pro + Facturación = `whatsapp_bot` + `whatsapp_inbox`. Cuando se definan los planes, `whatsapp_inbox` debe quedar **solo en el plan alto** (o como complemento pago), no necesariamente atado a facturación.

## 3. Costos de WhatsApp (Meta) — qué se paga y qué no

Desde el 1 de julio de 2025 Meta cobra **por mensaje entregado** según la categoría de la plantilla (página oficial: *Pricing on the WhatsApp Business Platform*, developers.facebook.com).

| Mensaje | ¿Lo cobra Meta? | En Zooetia |
|---|---|---|
| Plantilla **utility** que iniciamos nosotros (recordatorio de cita, de vacuna/control) | **Sí** | Es el costo principal. Lo cuenta `usage_counters` → `whatsapp_utility` |
| Plantilla **utility** enviada con la ventana de atención abierta | No (según Meta) | — |
| Mensaje libre (no plantilla) dentro de la **ventana de atención de 24 h** que abre el tutor al escribir o tocar un botón | **No**, según la página oficial de Meta. *Ojo:* sitios de terceros (Plivo, FormBeep, oct-2026) listan para Colombia una tarifa *service* de ~US$0.0008 después de 1.000 gratis al mes por número. **Confirmar en la tabla de tarifas de la cuenta al conectar la WABA.** | Respuestas del bot y del equipo. Se cuentan en `whatsapp_service` para ver el volumen |
| **Marketing** | Sí, la más cara (subió en Colombia el 1-oct-2025) | **No se usa** (el número es compartido y solo envía *utility*) |
| **Authentication** (códigos) | Sí | Evitado en el portal del tutor (ver `fase-4.md`, "Acceso del tutor") |

**La ventana de 24 h sí sigue existiendo.** Lo que desapareció en julio de 2025 fue el **cobro por "conversación" de 24 h**. La regla de que solo se puede escribir texto libre dentro de las 24 h siguientes al último mensaje del tutor (*customer service window*) sigue vigente. Pasado ese plazo, solo plantillas aprobadas (que se pagan). Por eso la bandeja bloquea la respuesta después de 24 h.

**Orden de magnitud (a confirmar con la tarifa oficial al conectar):** referencias de terceros ponen el *utility* en Colombia alrededor de **US$0.0008** por mensaje. 500 recordatorios ≈ **US$0.40/mes** por clínica. Si se confirma, el costo de Meta es menor que el de infraestructura y soporte; el límite `whatsapp_messages` sirve sobre todo para **proteger la calidad del número compartido** (si una clínica abusa, Meta puede limitar el número de todas).

## 3b. ¿WhatsApp se come el margen? Análisis (2026-10-03)

> Objetivo: margen cercano al **80 %** por suscripción, sin que WhatsApp se lo coma.

**Tarifas (revisadas el 2026-10-03):** utility en Colombia **US$0.0008** por mensaje entregado, según la tabla de tarifas de Meta reproducida por varios proveedores ([FormBeep](https://formbeep.com/whatsapp-api-pricing/), [EngageLab](https://www.engagelab.com/blog/whatsapp-business-api-pricing)). La página oficial de Meta dice que los mensajes libres y las utility dentro de la ventana de 24 h son gratis; algunos proveedores dicen que **desde el 1-oct-2026** se cobran a tarifa utility después de 1.000 gratis al mes **por número**. Como el número es **compartido** entre todas las clínicas, ese cupo gratis no alcanza: **asumimos que se cobran** (peor caso). Confirmar con la tabla oficial (CSV/PDF) al conectar la WABA.

**Consumo estimado de una clínica mediana** (15 citas al día, 20 días):

| Mensaje | Al mes | Tipo |
|---|---|---|
| Recordatorios de cita | ~300 | Plantilla utility |
| Recordatorios de vacuna / control | ~150 | Plantilla utility |
| Reserva en línea (recibida + aceptada/rechazada) | ~120 | Plantilla utility |
| Respuestas del bot (confirmar, reprogramar…) | ~400 | Servicio (peor caso: cobrado) |
| **Total** | **~970** | |

**Costo:** 970 × US$0.0008 ≈ **US$0.78 ≈ COP 3.100/mes** (a ~COP 4.000 por dólar).

| Plan | Precio | WhatsApp (estimado) | % del precio |
|---|---|---|---|
| Básico | 59.000 | 0 (sin automáticos) | 0 % |
| Pro | 99.000 | ~3.100 | **~3 %** |
| Pro + Facturación | 149.000 | ~3.100 | **~2 %** |

**Conclusión:** a las tarifas actuales WhatsApp **no** se come el margen: es ~3 % del precio. Para llegar al 80 % hay que mirar el resto de costos por clínica: **Mercado Pago (~3–4 % + IVA)**, servidor, correo, la factura DIAN de la suscripción y, sobre todo, el **soporte**. El riesgo real de WhatsApp es que **Meta suba la tarifa** (ya la subió en Colombia el 1-oct-2025) o que una clínica muy grande mande mucho más.

**Regla propuesta para proteger el margen:** WhatsApp ≤ **5 % del precio del plan**.
- Pro (99.000): tope de costo ≈ COP 5.000 ≈ US$1.25 ≈ **1.500 mensajes al mes**. El límite actual de 500 recordatorios automáticos deja mucho colchón.
- Si Meta multiplicara la tarifa por 10 (US$0.008), los ~970 mensajes costarían ~COP 31.000 (31 % del Pro): ahí sí habría que subir precio o vender **paquetes de mensajes**. Por eso conviene medir el costo real por clínica desde el primer día.

**Pendiente de implementar (propuesto):**
1. Guardar el **costo** de cada mensaje en `usage_counters.cost` (la columna ya existe) con la tarifa en configuración, y un reporte mensual de **costo y margen por clínica**.
2. Aviso a la clínica (campana) al **80 % y 100 %** de su límite de mensajes, y **paquetes de excedente**.
3. Contar también los mensajes de reserva en línea dentro del límite (hoy se cuentan, pero no se bloquean porque responden a algo que hizo el tutor).

### ¿Usar Twilio u otro intermediario? No (2026-10-03)

Twilio cobra **US$0.005 por cada mensaje, enviado o recibido**, además de la tarifa de Meta ([Landbot](https://landbot.io/blog/twilio-whatsapp-pricing), [Twilio](https://www.twilio.com/en-us/pricing/messaging)). En Colombia eso es **~6 veces** el costo de Meta por mensaje, y además cobra cada botón que toca el tutor.

| Con ~970 enviados + ~400 recibidos al mes | Meta directo (lo que ya está construido) | Con Twilio |
|---|---|---|
| Costo por clínica | ~US$0.78 ≈ **COP 3.100** (~3 % del Pro) | ~US$7.60 ≈ **COP 30.500** (~31 % del Pro) |

Zooetia ya habla **directo con la API de Meta** (`MetaWhatsAppProvider`): no paga intermediario. Twilio solo ahorraría el trámite inicial con Meta, que se hace una sola vez. **Decisión recomendada: seguir directo con Meta.**

### Presupuesto de costos por clínica para el 80 % (plan Pro, 99.000)

| Costo | Al mes | % |
|---|---|---|
| Pasarela de pago (~4 % + IVA) | ~4.000 | 4 % |
| WhatsApp (Meta directo) | ~3.100 | 3 % |
| Servidor, correo, copias (repartido) | ~1.000–2.000 | 1–2 % |
| Factura electrónica de la suscripción | ~200–500 | <1 % |
| **Queda para soporte y otros** | ~10.000 | ~10 % |
| **Margen** | **~79.000** | **~80 %** |

Supone que la suscripción **no** lleva IVA (ver `cobro.md` §6). El soporte es el costo que más hay que cuidar: guías dentro de la app, "primeros pasos" y respuestas rápidas.

## 4. Reglas que ya ahorran mensajes

- **Una sola vez por evento** (`reminders.event_at`): reprogramar o reabrir no reenvía lo mismo.
- **Se verifica todo justo antes de enviar:** consentimiento, mascota fallecida, cita cancelada, vacuna ya aplicada, plan y límite.
- **Vacuna o control con cita ya agendada (caso "Michi", 2026-10-02):** si la mascota ya tiene una cita futura (agendada o confirmada), **no** se envía el recordatorio de "¿te ayudamos a agendar?" (`skipped_has_appointment`): ya le llegará el de la cita. Si esa cita se cancela antes, el recordatorio sí sale.
- **Horario de envío** (8:00–19:00) y aviso mínimo: no se envía lo que ya no sirve.
- **El aviso "te responderán pronto"** se manda máximo una vez cada 2 h por conversación.

## 5. Para definir al crear los planes

- [ ] Precios finales (rangos de mercado en `info.md` §Planes).
- [ ] ¿`whatsapp_inbox` en el plan alto o como complemento pago?
- [ ] Límite de `whatsapp_messages` por plan y precio del excedente (paquetes).
- [ ] Confirmar tarifas reales de Meta para Colombia (utility y service) al conectar la WABA, y recalcular el margen.
- [ ] Presupuesto de costos por clínica para el 80 % de margen: Mercado Pago, servidor, correo, factura DIAN de la suscripción, soporte (WhatsApp ya estimado en ~3 %, §3b).
- [ ] ¿Cobrar las respuestas libres si Meta empieza a cobrarlas en Colombia? (ya se miden en `whatsapp_service`).
