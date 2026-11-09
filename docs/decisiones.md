# Registro de decisiones

Cada decisión importante, con fecha y motivo. Antes de cambiar algo de esta lista, hay que entender por qué se decidió y anotar aquí la nueva decisión (sin borrar la anterior: se marca como reemplazada).

Las decisiones del modelo de datos (D1–D10: UUIDv7, `clinic_id` en todo, catálogos mixtos, etc.) están en [`database.md` §0](database.md).

## Producto

| Fecha | Decisión | Por qué |
|---|---|---|
| 2026-09-30 | **Prueba gratis de 15 días** con funciones Pro; sin plan gratis permanente. Al vencer, solo lectura. | Un plan gratis permanente atrae clínicas que nunca pagan y cuesta soporte. Solo lectura evita que la clínica sienta que perdió sus datos. |
| 2026-09-30 | **Una persona puede trabajar en varias clínicas** ("con mucho cuidado"). | En Colombia es común que un veterinario atienda en dos o tres clínicas. Las salvaguardas están en `database.md` §3. |
| 2026-09-30 | **Ventana de edición de 24 h** para la historia clínica; luego solo el administrador. Los registros se anulan, no se borran. | Integridad legal de la historia clínica, sin impedir corregir un error el mismo día. |
| 2026-09-30 | **Número de WhatsApp compartido de Zooetia** para todas las clínicas. | Que cada clínica tramite su propia cuenta de WhatsApp Business API sería una barrera de entrada. Riesgo: reputación compartida → solo plantillas *utility* y topes por clínica. |
| 2026-09-30 | **Bot de WhatsApp sin IA en el MVP** (botones Confirmar / Reprogramar / Cancelar). IA después. | Cubre la mayor parte de las confirmaciones sin gastar tokens. |
| 2026-09-30 | **Portal del tutor y carné con QR** en la Fase 4. | Diferenciador frente a OkVet: el QR verificable de vacunas y el modo "mascota perdida". |
| 2026-09-30 | **Ventas como registro simple** de ingresos (no POS completo). | El foco del MVP es la parte clínica; la facturación electrónica viene en la Fase 5. |
| 2026-10-03 | **Toda reserva en línea es una solicitud** que aparta la hora; la clínica siempre la acepta o la rechaza, y las que no se responden no se vencen solas. | Aunque la agenda tenga huecos, la clínica puede no tener tiempo real; nadie llega a una cita que la clínica no aceptó. |
| 2026-10-03 | **La reserva pide documento además del WhatsApp.** | Identifica al tutor aunque cambie de celular y evita tutores duplicados. |
| 2026-10-03 | **El cobro en línea de la suscripción se integra después**; mientras tanto el plan se activa a mano tras el pago. Pasarela entre Mercado Pago y Wompi. | Permite vender desde ya sin bloquear el desarrollo por la pasarela. |
| 2026-10-03 | **WhatsApp directo con Meta, sin Twilio.** | Twilio cobra por mensaje además de Meta (~10 veces el costo por clínica); ver [`planes.md`](planes.md). |
| 2026-10-04 | **La Fase 5 se divide: 5a (inventario e ingresos, control interno) y 5b (facturación electrónica DIAN)**, la segunda con un contador. | La facturación electrónica es un tema legal y tributario que requiere asesoría; el control interno es útil desde ya. |
| 2026-10-04 | **El comprobante de venta dice que no es factura electrónica; los precios incluyen IVA.** | Evita confundir el comprobante con un documento tributario; en Colombia el público ve precios con IVA. |
| 2026-10-04 | **El inventario puede quedar en negativo** (se avisa y se corrige con un conteo). | La atención no se frena porque alguien no registró una compra. |
| 2026-10-05 | **"Ventas" (lo vendido) y "Recaudado" (lo cobrado) se muestran siempre separados.** Cierre del día en todos los planes; reporte del periodo y exportación desde Pro. | Con abonos y saldos pendientes no son el mismo número; el cierre de caja es de uso diario. |
| 2026-10-01 | **Gráficos de inicio**: solo los que responden una pregunta de la clínica (citas por día, inasistencia, servicios). Paleta validada para daltonismo. | Evitar el dashboard genérico "hecho por IA". Ver `design.md` §2b. |
| 2026-10-01 | **PDF clínico sobrio** (blanco y negro, un solo acento de color). | Las clínicas imprimen en láser monocromática y fotocopian. |
| 2026-10-02 | **El enlace de invitación solo viaja por correo**; la API no lo devuelve. | Quien tiene el enlace puede crear la cuenta con ese correo: el correo prueba que llegó a la persona correcta. Si se quisiera compartir por WhatsApp, habría que agregar verificación del correo con código. |
| 2026-10-02 | **El carné lo dibuja el navegador, no el servidor**, con tres plantillas, color a elección y medidas en milímetros reales. | El carné es una pieza de diseño (degradados, patrones, foto redonda) que dompdf no puede dibujar bien. Con CSS completo, lo que se ve en pantalla es exactamente lo que se descarga (PNG) e imprime (tamaño real con marcas de corte). |
| 2026-10-02 | **El carné se mostrará también en la app del tutor con el mismo diseño** (y antes en el portal web 4e); idea de Apple/Google Wallet. | El tutor debe poder mostrarlo fácil desde el celular. Como el diseño es datos (plantilla, color, opciones) y no una imagen, cualquier cliente lo redibuja igual y el QR siempre lleva al estado actualizado. |
| 2026-10-02 | **Fase 4: todo lo de WhatsApp se construye y prueba con un conector simulado**; la conexión a Meta (WABA) se hace al final. | El trámite de Meta es lo más lento (verificación de empresa, número, plantillas). No debe frenar el desarrollo. |
| 2026-10-02 | **Acceso del tutor al portal por invitación de la clínica, una sola vez**; después correo + contraseña con sesión larga. **Sin códigos por ingreso.** *(Reemplaza el diseño inicial de código por WhatsApp en cada ingreso.)* | Cada código por WhatsApp es un mensaje *authentication* que cobra Meta, multiplicado por ingresos y tutores; además cansa al usuario. El enlace llega solo a quien la clínica tiene registrado: eso prueba la identidad. Ver `portal.md`. |
| 2026-10-02 | **Validar al tutor con datos de la mascota (nombre, microchip) queda descartado.** | Se adivinan o están impresos en el carné: no prueban que la persona sea el tutor. |
| 2026-10-02 | **Recuperar contraseña con código por correo** (no WhatsApp). | El correo es gratis; WhatsApp sería otro mensaje *authentication* pagado. |
| 2026-10-02 | **La bandeja de WhatsApp (chat del equipo con el tutor) va en un plan superior**; el bot de botones queda en Pro. Sin bandeja, el texto libre recibe una respuesta con el WhatsApp propio de la clínica. | El costo de conversar es sobre todo tiempo del equipo (y quizá cobro de Meta a las respuestas libres en Colombia). El bot de botones no agrega costo y reduce inasistencias. Ver `planes.md`. |
| 2026-10-02 | **No se envía el recordatorio de vacuna/control si la mascota ya tiene una cita futura** (caso "Michi"). | "¿Te ayudamos a agendar?" sobra si ya agendó; ya le llegará el de la cita. Ahorra mensajes pagados. |
| 2026-10-02 | **Vacunas ordenadas por importancia: rabia primero, luego la más reciente**; si no caben en el carné, "+N vacunas más · escanea el QR". | El orden alfabético podía esconder la de rabia, que es la que piden guarderías y viajes; y ocultar vacunas sin avisar confunde. |
| 2026-10-02 | **El carné del portal se voltea con animación 3D** (sin animación si el teléfono pide reducir movimiento). | Se siente como un carné de verdad; respeta la accesibilidad. |
| 2026-10-03 | **Consentimientos firmados en pantalla** (eutanasia, cirugía y anestesia, hospitalización, alta voluntaria), en **todos los planes**; primero en la clínica, luego a distancia. | Visto en VetPraxis. Es de uso diario, no gasta mensajes de Meta y es un diferenciador aun en el plan básico. |
| 2026-10-03 | **Firma electrónica simple con evidencias** (Ley 527 de 1999), no firma digital con certificado. | Para un consentimiento veterinario basta una firma confiable: identidad, contenido (hash), fecha, dispositivo e intención. Una firma digital certificada encarece y complica sin necesidad. Textos pendientes de revisión legal. |
| 2026-10-03 | **Tutor con documento y veterinario con tarjeta profesional son obligatorios** para crear un consentimiento. | Identifican a quien autoriza y a quien responde; un consentimiento sin ellos pierde valor. |
| 2026-10-03 | **Firma a distancia con enlace de un solo uso + últimos 4 dígitos del documento**; en el portal, sin dígitos. El número de documento se enmascara en la página del enlace. | El enlace prueba que llegó al teléfono o correo del tutor; los dígitos, que quien firma es el tutor. Mostrar el número completo en esa página anularía la verificación. |
| 2026-10-03 | **La clínica edita solo el texto** de sus consentimientos (no los campos ni los firmantes), y siempre debe nombrar al tutor, su documento y la mascota. | Da flexibilidad a cada clínica (su abogado, su estilo) sin poder romper un documento ni quitarle lo que lo hace válido. |

## Técnicas

| Fecha | Decisión | Por qué |
|---|---|---|
| 2026-09-29 | **Laravel API + una sola app Next.js** (no Blade/Inertia). | SEO para las páginas públicas y la reserva online; la misma API sirve a la futura app móvil. |
| 2026-09-29 | **Monorepo** (`api/`, `web/`, luego `mobile/`). | Un solo repositorio, un solo historial, cambios de API y frontend en el mismo commit. |
| 2026-09-29 | **Pest** para tests. | Sintaxis más legible que PHPUnit puro; corre sobre PHPUnit. |
| 2026-09-29 | **PostgreSQL** (no MySQL). | `timestamptz`, `jsonb`, FKs compuestas, trigramas para búsqueda tolerante a errores, CHECKs. |
| 2026-09-29 | **Sin Docker** en la máquina de desarrollo (Windows 10). | Simplicidad; PHP nativo y PostgreSQL portable. |
| 2026-09-30 | **Tokens de Sanctum atados a una clínica**; la clínica nunca va en la URL. | Imposible pedir datos de otra clínica cambiando un parámetro. |
| 2026-09-30 | **Scope de clínica que falla cerrado** + FKs compuestas + test de esquema. | Varias capas: si una falla, la siguiente detiene la fuga de datos. Ver `arquitectura.md` §3. |
| 2026-09-30 | **Todo en UTC, se muestra en la hora de la clínica.** | Las clínicas pueden estar en otra zona horaria o el usuario viajando; los agrupamientos por día se hacen en hora local. |
| 2026-10-01 | **Base de datos local para desarrollo**; el VPS para staging. | Desde la máquina de desarrollo, cada consulta al VPS tardaba ~190 ms (login de 13 s, suite de tests de 30 min). |
| 2026-10-01 | **BFF con cookie httpOnly** (el navegador no ve el token). | Un XSS no puede robar la sesión. |
| 2026-10-01 | **TailAdmin Free como base visual**, verde bosque + coral, Plus Jakarta Sans. | Componentes probados y licencia MIT; la paleta y la fuente le dan identidad propia. |
| 2026-10-01 | **Agenda propia, sin librería de calendario.** | La vista por profesional (columnas) es de pago en FullCalendar; además la agenda debe usar la hora de la clínica, no la del navegador. |
| 2026-10-01 | **PDF en el servidor con dompdf.** | Resultado idéntico siempre, sin navegador en el servidor, y cada exportación queda auditada. |
| 2026-10-01 | **Imágenes re-codificadas al subirlas** (WebP/PNG con GD). | Quita metadatos GPS de fotos de celular, reduce tamaño y descarta archivos que no son imágenes. |
| 2026-10-02 | **Colas con driver `database`** hasta tener staging. | Funciona en Windows sin Redis; se cambia por Redis + Horizon en el servidor sin tocar código. |
| 2026-10-02 | **El bot de WhatsApp no guarda estado de conversación**: cada botón lleva en su *payload* la acción y el id de nuestro mensaje (`confirm:{id}`, `slot:{id}:202610050800`). | Con un número compartido, el botón dice de qué clínica y cita se trata. Tocar un botón viejo funciona igual y no hay conversaciones "a medias". |
| 2026-10-02 | **Webhook de Meta: firma obligatoria, cola y una sola vez por mensaje** (`provider_message_id`). | Meta reintenta los webhooks; sin firma cualquiera podría confirmar o cancelar citas. |
| 2026-10-02 | **Tokens del portal separados de los del personal** (habilidad `tutor`, sin clínica, cookie `zoo_tutor`, BFF `/api/tutor` que solo alcanza rutas `portal/*`). | Un tutor nunca puede llamar el dashboard ni al revés, aunque compartan Sanctum. |
| 2026-10-02 | **Enlaces de invitación y códigos se guardan como hash** (sha256 / HMAC con la APP_KEY). | Una fuga de la base de datos no entrega enlaces ni códigos usables. |
| 2026-10-02 | **Las funciones de pago se preguntan por *feature*, nunca por nombre de plan** (`Subscription::allows('whatsapp_inbox')`). | Se pueden crear y reorganizar planes sin tocar código. |
| 2026-10-03 | **El documento congela su texto al crearse; la primera firma lo bloquea; el PDF firmado se genera una sola vez** y se entrega siempre el mismo archivo. | Lo que el tutor lee, lo que firma y lo que se imprime deben ser idénticos y verificables (huella SHA-256). |
| 2026-10-03 | **Lienzo de firma propio** (sin librería). | ~100 líneas que controlamos: nitidez en pantallas de alta densidad, trazo suave, no se borra con la barra del celular. |
