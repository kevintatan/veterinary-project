# Historial del proyecto

Cómo se construyó Zooetia: qué se hizo en cada fase, **qué se decidió** y **qué errores aparecieron y cómo se corrigieron**. Sirve para que el equipo entienda el porqué de lo que hay, sin tener que leer todo el historial de git.

- Qué hace hoy la aplicación: [`funcionalidades.md`](funcionalidades.md).
- Decisiones con su motivo: [`decisiones.md`](decisiones.md).
- Cada cambio exacto: `git log` (los mensajes de commit están en español).

**Forma de trabajo acordada:** una rama por fase; se une a `master` cuando todas las pruebas pasan. Pasos pequeños y verificados, plan antes de cada fase, todo documentado para el equipo. Cada pantalla se prueba en el navegador antes de darla por terminada.

---

## Fase 1 — Cimientos (2026-09-30)

**Qué se construyó (API Laravel 13 sobre PostgreSQL):**
- **Multi-clínica (*multi-tenant*)**: cada dato lleva su clínica; el filtro por clínica "falla cerrado" (si falta la clínica, la consulta se detiene en vez de mostrar todo), llaves foráneas compuestas en la base de datos y una prueba que recorre el esquema y falla si una tabla nueva no tiene clínica.
- **Autenticación multi-clínica**: una persona puede trabajar en varias clínicas; el token de sesión queda atado a una clínica (nunca va en la URL).
- Roles y permisos (Administrador, Veterinario, Recepción), sedes, equipo, invitaciones por correo.
- Suscripción con **prueba gratis de 15 días**; al vencer, solo lectura.
- Pruebas automáticas contra PostgreSQL local, sesión de base de datos forzada a UTC.

## Fase 2 — Núcleo clínico, API (2026-09-30)

- **2a** Mensajes en español y catálogos (especies, razas, vacunas, procedimientos, alertas): globales + propios de cada clínica.
- **2b** Tutores con **Habeas Data** (autorizaciones por canal con historial), mascotas, alertas y búsqueda tolerante a errores.
- **2c** Agenda: estados con transiciones válidas, historial de cada cita, cruces de horarios y disponibilidad.
- **2d** Historia clínica: consulta SOIP, vacunas, desparasitaciones, fórmulas, procedimientos; **ventana de edición** y **anulación** (nunca se borra).
- **2e** Tareas pendientes (API).

## Fase 3 — Dashboard web (2026-10-01 y 02)

**Qué se construyó (Next.js 16 + React 19 + Tailwind 4, base TailAdmin):**
- **3a** Login con BFF (el token vive en una cookie que el navegador no puede leer) e inicio **"Hoy en la clínica"**, luego con la sección **Actividad** (gráficos e indicadores con paleta validada para daltonismo).
- **3b** Agenda: día por veterinario y semana; crear, reprogramar, cambiar estados.
- **3c** Tutores y mascotas: búsqueda, fichas, formularios, fotos y alertas.
- **3d** Historia clínica: línea de tiempo, registros por tipo, anulación, adjuntos, **PDF**.
- **3e** Configuración: clínica, sedes, equipo, horarios, servicios, bloqueos y perfil.
- Documentación para el equipo (`docs/`) y plan de la Fase 4.

**Decisiones y ajustes:**

- **Agenda propia**, sin librería (la vista por profesional es de pago en FullCalendar y la agenda debe usar la hora de la clínica), con **vista de mes**, **mini calendario** para saltar a cualquier fecha y **horarios libres sugeridos**.
- El detalle de la cita pasó de un modal a un **panel lateral** con más información y acciones.
- **PDF de la historia** completo (por periodo y tipo) y por registro, con ícono PDF en los botones y **estilo sobrio** (blanco y negro con un solo acento): las clínicas imprimen en láser monocromática.

## Fase 4 — Diferenciadores (2026-10-02)

**Estrategia:** el trámite de Meta es lo más lento, así que **todo lo de WhatsApp se construyó y probó con un conector simulado**; conectar la cuenta real es el último paso. Todo documentado para que el equipo lo entienda.

### 4a — Recordatorios manuales por WhatsApp
Botón "Recordar" que abre el WhatsApp de la clínica con el mensaje escrito; tarjeta "Para recordar" en el inicio; bitácora de mensajes.

### 4b — Carné digital con QR
Página pública del QR con el estado de vacunas verificado, modo mascota perdida, escaneos, QR regenerable.

- **Diseñador de carné** con 3 plantillas (Clásico, Moderno, Huellitas), 10 colores o uno propio, foto opcional, PNG e impresión a tamaño real (8,6 × 5,4 cm) con marcas de corte. El carné lo dibuja el navegador en milímetros reales.
- La foto de la mascota se carga desde la ficha, el formulario y el diseñador.
- El diseño se guarda como datos: el portal (4e) muestra **el mismo carné**.

### 4c — Recordatorios automáticos
Reglas por clínica (24 h antes de la cita, 7 días antes del control), horario de envío 8:00–19:00, reintentos, límite del plan, programados/enviados/no enviados con el motivo.

### 4d — Bot de confirmación y bandeja
Recordatorio con botones Confirmar / Reprogramar / Cancelar; el bot reprograma ofreciendo horarios libres reales; webhook de Meta firmado; bandeja para el equipo; simulador de tutor.

**Decisiones:**

- **Acceso del tutor sin códigos por ingreso:** cada código por WhatsApp es un mensaje que cobra Meta. Se verifica una sola vez por invitación de la clínica y luego tiene sesión larga (ver 4e).
- **La bandeja de chat va en un plan superior:** lo que cobra Meta son las plantillas (recordatorios), pero el costo real de conversar es el tiempo del equipo. Sin bandeja, el texto libre recibe una respuesta con el WhatsApp de la clínica. Detalle en [`planes.md`](planes.md).
- **Ventana de 24 h:** desapareció el **cobro** por conversación de 24 h (julio 2025), pero la **regla** de solo poder escribir texto libre dentro de las 24 h sigue vigente.
- **Caso "Michi":** no se envía el recordatorio de vacuna o control si la mascota ya tiene una cita futura. Si la cita se cancela, sí sale.
- Nuevo documento [`planes.md`](planes.md): funciones por plan, costos de Meta y decisiones pendientes.

### 4e — Portal del tutor
Acceso por invitación de la clínica (WhatsApp propio, correo o enlace), una cuenta para todas las clínicas, mascotas, carné, vacunas y citas; recuperar contraseña por correo.

- Cuenta demo del portal: Paola (dueña de Rocky).
- El carné se voltea con **animación 3D** (sin animación si el teléfono pide reducir movimiento).
- **Muchas vacunas en el carné:** antes se ocultaban sin avisar y en orden alfabético (podía esconder la de rabia). Ahora va **rabia primero, luego la más reciente**, y "+N vacunas más · escanea el QR". Rocky tiene 5 vacunas en los datos demo para verlo.

### 4f — Pantalla de Tareas
Mías / creadas por mí / toda la clínica; vencidas, hoy, próximas, sin fecha; completar con un clic.

---

## Documentos y firmas (2026-10-03)

**Origen:** en VetPraxis el propietario firma las autorizaciones en la pantalla. La firma electrónica es válida en Colombia (Ley 527 de 1999) si se guardan las evidencias. Se decidió empezar por **eutanasia, cirugía y anestesia, hospitalización y alta voluntaria**, primero la firma en la clínica (D1–D2) y luego a distancia (D3), en **todos los planes**.

**Qué se construyó (D1–D2):** plantillas con campos por caso y datos que se llenan solos; documento con el texto congelado; firma en pantalla (lienzo propio, sin librería) del tutor, el veterinario desde su sesión y un testigo opcional; bloqueo del texto con la primera firma; PDF firmado generado una sola vez con hoja de evidencias (huella SHA-256 del contenido, fecha, dispositivo, IP); anulación con motivo. Guía: [`documentos.md`](documentos.md).

**Errores encontrados al probarlo en el navegador y en el PDF:**

| Qué pasaba | Corrección |
|---|---|
| El trazo de la firma empezaba y terminaba con un hueco (un punto suelto y luego la línea) | Se dibujan el primer y el último tramo de cada trazo |
| Si la pantalla se re-dibujaba, el lienzo podía borrar la firma a medio hacer (y en celulares, al moverse la barra del navegador) | El lienzo solo se reinicia si cambia el ancho real; el aviso al padre va por referencia |
| En el PDF la firma del veterinario salía antes que la del tutor | PostgreSQL reordena las llaves de `jsonb`: el orden se fija en código |
| La evidencia decía "navegador desconocido" e IP del servidor | El proxy de Next ahora reenvía el navegador y la IP del visitante |
| "11:28 a. m.." en el PDF; nombre del tutor partido en dos líneas | Punto sobrante quitado; anchos de columna fijos |

**D3 — Firma a distancia (mismo día):** enlace de un solo uso (48 h, 5 intentos) por WhatsApp de la clínica, correo o copiado; el tutor confirma los últimos 4 dígitos de su documento; en el portal firma sin dígitos y ve sus documentos firmados con el PDF. Probado de punta a punta: hospitalización de Rocky firmada por Paola desde el enlace (con un intento fallido de dígitos) y alta voluntaria firmada desde el portal.

Encontrado y corregido en D3: **el texto del consentimiento mostraba el número de documento completo en la página del enlace**, justo el dato cuyos últimos 4 dígitos se piden para verificar; ahora se enmascara solo en esa página. También: la "X" de cerrar tapaba el texto en las ventanas de firma, y el lienzo ahora tolera navegadores que no permiten capturar el puntero.

**Rediseño del editor** (más fácil de usar, campos más grandes y vista previa con aspecto de documento): ahora se edita **sobre la hoja** (tamaño carta, membrete, tabla, firmas y pie como el PDF); clic en un párrafo para editarlo en su lugar con la misma letra; datos como «Etiquetas» legibles en vez de códigos; ejemplos resaltados en la hoja; panel lateral de datos y barra de acciones fija. Ajustes al probarlo: los datos del caso se resaltaban en rojo (parecía error) y pasaron a ámbar; la barra de herramientas tapaba la primera línea del párrafo en edición (ahora va abajo); el texto justificado estiraba las líneas dentro de la caja de edición (ahora alineado a la izquierda mientras se edita).

**D4 — Textos propios de cada clínica (mismo día):** editor en Configuración → Documentos con datos que se insertan con un clic, párrafos condicionales, vista previa en vivo y "Restaurar texto de Zooetia"; se validan datos inexistentes y que el texto identifique al tutor y la mascota. Corregido al probar: al insertar un dato podía ir al último párrafo en vez de donde estaba el cursor (ahora se mira qué caja tiene el cursor en el momento del clic), y el aviso "Guardado" se perdía al recargarse el editor.

El primer documento de prueba (eutanasia de Michi) se **anuló** con su motivo porque su PDF se generó antes de corregir el orden: un documento firmado nunca se modifica.

---

## Errores encontrados y corregidos (Fases 3 y 4)

Para que el equipo sepa qué se aprendió y no lo repita.

| Dónde | Qué pasaba | Corrección |
|---|---|---|
| Recordatorios | Un refuerzo de rabia se ocultaba al aplicar **otra** vacuna después | Solo lo resuelve una aplicación **de la misma vacuna** |
| Texto de recordatorios | Doble punto después de "a. m." | Se reorganizó la frase (la hora va antes de la fecha) |
| Pruebas | Funciones de prueba con el mismo nombre en dos archivos (y una choca con `localtime` de PHP) | Nombres con prefijo por archivo (`ar…`, `ownerTaps`) |
| Ficha de mascota | Subir la foto devolvía la mascota sin alertas y rompía la ficha | La pantalla solo toma la nueva foto de la respuesta |
| Historia clínica | En clínicas con varias sedes los registros exigían sede y el formulario no la pedía | Selector de sede en el formulario |
| Vacunas | Enviar "próximo control" vacío bloqueaba el valor por defecto | Se omite si está vacío |
| Página del QR | Laravel veía la IP del servidor de Next, no la del visitante | Se reenvía la IP y Laravel solo confía en los proxies configurados |
| Página del QR | El escaneo justo después de activar "perdida" no se contaba | La deduplicación es por modo |
| Bot de WhatsApp | El id del mensaje con botones no se guardaba (no era asignable) | Se fija antes de enviar y se guarda con `forceFill` |
| Bandeja | El "Reprogramar" del tutor aparecía después de la respuesta del bot | Desempate por id (los timestamps tienen 1 s de precisión) |
| Bandeja | Un `useEffect` devolvía una promesa (`scrollIntoView` en Chrome nuevo) y React fallaba | Bloque explícito y desplazamiento solo del chat |
| Recuperar contraseña | Un intento fallido se "deshacía" al revertirse la transacción | La transacción devuelve el resultado y el error se lanza afuera |
| Portal | Los textos del carné salían centrados (los botones centran por defecto) | `text-left` en el contenedor del carné |
| Ficha de mascota | "Próximas citas" apretaba el nombre del servicio en la columna angosta | Dos líneas: cuándo / qué |
| **Todos los paneles laterales** | Al abrirse se quedaban con el foco y se perdían las primeras teclas | El panel solo toma el foco si nada adentro lo tiene |
| Tareas | El buscador de tutor se llevaba el foco del título; el panel se desbordaba | `autoFocus` opcional en el buscador; fecha y hora en su propia fila |
| Documentación | El catálogo mencionaba una pantalla de registro que no existe | Corregido: el registro solo está en la API (pendiente la pantalla) |

---

## Registro de clínicas y cobro (2026-10-03, rama `registro-y-cobro`)

Decisión: el cobro en línea se integra más adelante (pasarela por definir; ver [`cobro.md`](cobro.md) §6). Mientras tanto, la clínica solicita el plan y el equipo lo activa a mano.

- **R1 — Registro:** pantalla `/registro` (enlace desde el login), aceptación obligatoria de términos y política de datos con fecha y versión guardadas (Ley 1581), páginas `/terminos` y `/privacidad` con texto base, entrada directa a la app con bienvenida y tarjeta **"Primeros pasos"** que se marca sola.
- **R2 — Plan y facturación:** pantalla con estado, uso del mes y planes; "Elegir plan" deja una solicitud (sin pago aún); activación manual con `subscriptions:activate` (renovar suma después del fin del período); estado **pago pendiente** con 5 días de gracia y vencimiento nocturno; insignia en la barra superior ("Prueba gratis", "Pago pendiente", "Modo consulta · Activar plan").
- **Landing (`/`):** antes `/` redirigía a la app. Ahora es la página pública: encabezado con anclas, portada con imagen de la app hecha en HTML (agenda, burbuja de WhatsApp), franja de confianza (Ley 1581, Ley 527, WhatsApp oficial, nube), 8 funciones, tres secciones destacadas (WhatsApp, consentimientos, carné y portal), "cómo empezar", **precios desde la base de datos**, preguntas frecuentes, llamado final y pie con enlaces legales. Funciona en celular (menú desplegable) y respeta "reducir movimiento". **Por validar con el equipo:** las promesas de las preguntas frecuentes (copias de seguridad diarias, acompañamiento por WhatsApp, sin permanencia) y que no hay testimonios ni cifras reales todavía.
- **Corregido al probar:** al vencerse una prueba, la app dejaba de mostrar la suscripción (solo contaba las vigentes), así que la clínica no veía por qué no podía editar; ahora se muestra la última. La prueba cancelada y el plan nuevo se creaban en el mismo segundo y a veces se tomaba la equivocada: se desempata por el id (ordenado en el tiempo). En la comparación de planes, Básico repetía "sin mensajes de WhatsApp" dos veces.
- **Probado en el navegador:** registro de "Veterinaria Patitas" (Laura), bienvenida y primeros pasos, solicitud del plan Pro, activación manual, estado "activo", y modo consulta con su aviso.

## Reserva en línea B1 y campana de notificaciones (2026-10-03)

Objetivo: que el tutor pida la cita desde un enlace (servicio, día y hora) y siga el mismo flujo de confirmación por WhatsApp. Decisiones: **la clínica siempre acepta o rechaza** cada solicitud (aunque la agenda esté abierta, puede no haber tiempo real) y se agrega **una campana de notificaciones**. Detalle en [`reservas.md`](reservas.md) y [`notificaciones.md`](notificaciones.md).

- Nuevo estado **"Solicitud en línea"** que aparta la hora; aceptar → "Programada" (con recordatorios normales); rechazar → cancelada con motivo.
- Página pública `/reservar/{clínica}` para celular; Configuración → Reserva en línea; Agenda → Solicitudes con sugerencia del tutor por celular (solo para la clínica: la página pública nunca revela clientes).
- Campana con notificaciones por persona y por clínica.
- **Ajustes al probar:** el límite de 3 envíos por minuto era muy estricto para alguien que corrige errores del formulario (subió a 8); "cualquiera disponible" asignaba todo al primer profesional en orden alfabético (ahora al que tiene menos citas ese día); doble punto en "a. m.." en la notificación y en la fecha de la solicitud.
- **Documento al reservar:** tipo y número obligatorios; sugerencias por documento primero y luego por celular; sin tutores duplicados por documento; opción "Actualizar su celular" cuando la persona cambió de número (si no, los recordatorios irían al número viejo); completar el documento de un tutor que no lo tenía. Probado: Paola reservó con su cédula escrita con puntos y un celular nuevo; la clínica la reconoció por documento, con el aviso de celular distinto y sin opción de crear un duplicado. Decisión: **las solicitudes sin respuesta se quedan pendientes** (no se vencen solas).
- **Probado en el navegador:** activar en Anipet, pedir Vacunación para Rocky como Paola (celular registrado), campana y badge, sugerencia "Paola Andrea Díaz · Rocky (mismo nombre)", aceptar y verla programada en la agenda; segunda solicitud (Toby) vista punteada en la agenda. **Quedó pendiente en la demo** la solicitud de Toby (lunes 5, 10:00) para que el equipo la pruebe.

## Reserva en línea B2 y campana en vivo (2026-10-03)

Objetivo: avisar al tutor por WhatsApp en cada paso y que la clínica se entere sin refrescar la pantalla.

- **B2 — WhatsApp al tutor:** solicitud recibida (con botón "Cancelar solicitud"), aceptada (con Reprogramar / Cancelar) y rechazada (con el motivo y el enlace para pedir otra hora). Tres plantillas nuevas para Meta (`whatsapp.md` §3). El bot entiende la cancelación de una solicitud y avisa a la clínica.
- **La campana ahora avisa a la clínica** cuando un tutor cancela o reprograma por WhatsApp (tipo `whatsapp_reply`).
- **Campana en vivo, sin refrescar:** consulta cada 20 s con la pestaña visible y al volver a ella; aviso emergente, sonido, número en el título de la pestaña y avisos de escritorio opcionales. Para producción queda propuesto el tiempo real con WebSockets (Reverb).
- **Corregido al probar:**
  - Un trabajo de la cola ejecutado dentro de la misma petición (cola síncrona) **borraba el contexto de la clínica de la petición** al terminar → ahora lo restaura en ese caso, y en el trabajador de la cola sigue borrándolo (protección contra contextos sobrantes).
  - El simulador de WhatsApp solo aceptaba celulares de tutores registrados; ahora también los de solicitudes en línea (quien pide una cita aún no es tutor).
  - Los mensajes decían "10:00 a. m.." (la frase terminaba en la hora) → la fecha va al final, como en los recordatorios.
- **Probado en el navegador:** solicitud de Camila (Luna) aceptada con sus dos WhatsApp en el registro; solicitud de Andrés (Max) con la pantalla abierta: a los 17 s apareció el aviso, el título pasó a "(2)" y la campana y la agenda se actualizaron solas. Quedan pendientes en la demo las solicitudes de **Toby** (lunes 5, 10:00) y **Max** (miércoles 7, 11:00).

## Reserva en línea B3 (2026-10-03)

Objetivo: pedir cita desde el portal, QR para el mostrador y landing al día. Decisión: en el servidor, las notificaciones pasan a tiempo real con WebSockets (ver [`notificaciones.md`](notificaciones.md)).

- **Portal del tutor:** "Pedir una cita" en el inicio y "Pedir una cita para Rocky" en el carné; mismo formulario con nombre, celular, documento y mascotas ya llenos. Probado con Paola: la clínica recibió la solicitud con "mismo documento y celular" y Rocky sugerido (la solicitud de prueba se rechazó para no ensuciar la demo).
- **QR y cartel:** QR del enlace en Configuración → Reserva en línea (descargar PNG) y cartel tamaño carta para el mostrador.
- **Landing:** la reserva en línea dejó de ser "próximamente"; tarjeta y pregunta frecuente nuevas. El catálogo de planes en la landing se refresca cada 10 minutos (antes cada hora: el "pronto" tardaba en desaparecer).
- **Decisión:** las notificaciones pasarán a **tiempo real con WebSockets (Laravel Reverb)** al montar el servidor; el plan está en `notificaciones.md`.

## Alerta de GitGuardian (2026-10-03)

GitGuardian avisó por correo de "Generic Password" en varios commits subidos a GitHub (e27d340, fd7ce6e, 7f984e7, 902bee1, 4438720). **Revisado: no son contraseñas reales.** Son claves inventadas de **pruebas automáticas** (`Kira2026x`, `Nueva2026x`, `Clave2026x`…) y de los **datos demo locales** (`password`, `password1`). Los `.env` con las claves reales **nunca** se han subido (solo los `.env.example`, vacíos).

- **Endurecido:** todos los seeders de demostración (`Demo*Seeder`) se niegan a correr fuera de local y pruebas, aunque alguien los ejecute a mano en el servidor (prueba en `tests/Feature/DemoSeedersTest.php`). Así las cuentas demo con contraseñas conocidas nunca existen en producción.
- **Qué hacer en GitGuardian:** marcar esas alertas como *test credential* / falso positivo. Pueden aparecer más con cada subida que tenga pruebas con contraseñas: revisar siempre que sean de pruebas o demo.
- **Regla del equipo:** nunca poner una contraseña o token real en el código, los documentos ni los commits; siempre en `.env`.

## Fase 5a · V4 — Reportes y exportación (2026-10-05) · Fase 5a completa

- **Cierre del día** (recepción y administrador, todos los planes): recaudado por medio de pago y por quien recibió, abonos a ventas anteriores, ventas, por cobrar y anulaciones; imprimible sin menú.
- **Ventas del periodo** (administrador, desde Pro): comparación con el periodo anterior, ventas por día, lo más vendido, por tipo, medio de pago y vendedor.
- **Exportación a Excel** (CSV con `;` y decimales con coma) de ventas por ítem y de pagos.
- **"Recaudado hoy"** en el inicio.
- Decisión de redacción: **"Ventas"** (lo vendido) y **"Recaudado"** (lo cobrado) se muestran siempre separados y explicados.
- **Corregido al probar:** el menú mostraba "WhatsApp" un instante mientras cargaba aunque el plan no lo incluyera (ahora solo aparece si el plan lo incluye); en la agenda del inicio, una solicitud en línea decía "Espacio reservado" en vez del nombre de la mascota pedida.
- **Probado en el navegador:** tarjeta del inicio ($140.000 recaudado, $220.000 vendido, $80.000 por cobrar), cierre del día, reporte del mes con gráfico y rankings, y las dos exportaciones.

## Fase 5a · V3 — Conexión con la atención (2026-10-05)

- **Cuenta del día**: lo que se cobra de una visita va a una sola cuenta del tutor (hoy, misma sede, sin pagar del todo).
- **"Cobrar" desde la cita** con el servicio ya cargado; si ya se cobró, lleva a la misma venta.
- **Producto del inventario en vacunas, desparasitaciones y procedimientos**: llena nombre y lote; se carga a la cuenta o solo se descuenta del inventario si va incluido; todo en la misma transacción que el registro.
- **Ajuste visual previo:** los botones "Anular pago" y "Anular venta" eran texto casi invisible → botones con borde rojo.
- **Corregido al probar:** una función auxiliar de las pruebas nuevas tenía el mismo nombre que otra existente y rompía la ejecución completa.
- **Probado en el navegador:** "Cobrar" en la cita de Rocky (9:00, vacunación) creó la venta N.º 000002; luego, al registrar la vacuna en la historia eligiendo la antirrábica del inventario, se llenaron nombre, lote RB-2611 y vencimiento, y la vacuna se sumó a la misma venta ($45.000 + $35.000). La venta quedó pendiente en la demo para probar el cobro.

## Fase 5a · V2 — Ventas y pagos (2026-10-05)

- Ventas a tutores o de mostrador con consecutivo por clínica; productos (descuentan existencias), servicios y cargos libres; descuentos; IVA incluido calculado por ítem.
- Pagos parciales por cualquier medio, con ayuda para el cambio en efectivo; la venta pagada queda cerrada.
- Anulación de pagos y ventas con motivo (solo administrador), devolviendo productos al inventario.
- Comprobante PDF en media carta con la leyenda "no es factura electrónica".
- Tarjeta **Cuenta** en la ficha del tutor.
- **Corregido al probar:** en el comprobante, la columna "Total" del encabezado no alineaba con los valores, el celular salía sin formato y la hora decía "am" en vez de "a. m."; en la pantalla de la venta el celular también salía sin formato.
- **Probado en el navegador:** venta para Paola desde su ficha (NexGard, consulta y un cargo libre: $140.000 con $10.857 de IVA incluido), pago por Nequi (quedó "Abonada"), pago en efectivo con cambio calculado (quedó "Pagada" y bloqueada) y comprobante PDF revisado.

## Fase 5a · V1 — Productos e inventario (2026-10-03)

La Fase 5 se dividió en dos: **5a, inventario e ingresos** (sin documentos legales ante la DIAN; al tutor se le entrega un comprobante que dice "no es factura electrónica") y **5b, facturación electrónica**, que se hará después con un contador y un proveedor autorizado. Decisiones de la 5a en [`inventario.md`](inventario.md).

- **V1:** productos, existencias por sede, entradas con lote y vencimiento, ajustes (solo administrador), traslados, alertas de existencias bajas y lotes por vencer, aviso diario en la campana e importación desde Excel (CSV).
- **Corregido al probar:**
  - Nombres repetidos con tildes ("VACUNA MÚLTIPLE" frente a "Vacuna múltiple") no se detectaban porque la base de datos no pasa a minúscula la "Ú" → clave normalizada del nombre calculada en PHP, con índice único.
  - Una sede que nunca ha manejado un producto lo marcaba "agotado" y generaba alertas diarias → ahora dice "no se maneja aquí" y no alerta.
  - Las cantidades decían "6 Tableta" → "6 tabletas".
- **Probado en el navegador:** importación de 7 filas (6 creadas, 1 con error explicado), entrada con lote y vencimiento, traslado a otra sede, conteo físico (de 9 a 8), alerta del lote por vencer y aviso diario en la campana.

## Pendientes al cierre de la Fase 4

- **Conectar Meta (WABA)**: trámite del equipo; paso a paso en [`whatsapp.md`](whatsapp.md).
- ~~Subir `master` a GitHub~~ (hecho el 2026-10-03).
- **Definir planes y precios** con [`planes.md`](planes.md).
- ~~Pantalla de registro de clínicas~~ (hecho). **Pago con Mercado Pago**: ver [`cobro.md`](cobro.md).
- Portal: fórmulas y documentos compartidos; modo perdida desde el portal; recuperar acceso si el tutor perdió su correo.
- **Documentos**: **revisión legal** de los textos de Zooetia.
- **Fase 5**: inventario, ingresos y facturación electrónica DIAN.
