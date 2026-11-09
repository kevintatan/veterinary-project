# Módulos: qué hace cada pantalla y sus reglas

Guía funcional para el equipo (producto, soporte, ventas y desarrollo). Cada módulo dice **qué hace**, **las reglas de negocio** que hay que respetar y **dónde está el código**.

---

## Acceso, clínicas y suscripción

**Qué hace.** Registro de una clínica nueva con prueba gratis de 15 días, login, selección de clínica cuando la persona trabaja en varias, invitaciones al equipo.

**Reglas.**
- Una **persona** (usuario) puede pertenecer a varias clínicas con roles distintos; el login le pregunta cuál abrir y el menú del usuario permite cambiar.
- Roles: **Administrador** (todo), **Veterinario** (agenda, pacientes, historia clínica), **Recepción** (agenda y pacientes; no escribe historia clínica).
- Invitar: el administrador escribe un correo, un rol y las sedes. Llega un enlace válido **7 días**. Si la persona no tiene cuenta, la crea en esa página; si ya tiene, inicia sesión y acepta. Una invitación nueva al mismo correo reemplaza la anterior.
- El enlace de invitación **solo viaja por correo** (la API nunca lo devuelve): el correo prueba que la invitación llegó a la persona correcta.
- Con la prueba o la suscripción vencida, todo sigue visible pero **las escrituras se bloquean** (402) hasta pagar.

**Código.** API: `AuthController`, `InvitationController`, `Actions/Members/*`, `Actions/Clinics/RegisterClinic`. Web: `app/(auth)/*`, `app/api/auth/*`.

---

## Hoy en la clínica (inicio)

**Qué hace.** Lo que pasa hoy: contadores (citas, por confirmar, en sala, atendidas), agenda del día, sala de espera y mis tareas. Debajo, **Actividad** con filtro de 7/30/90 días: citas atendidas, tasa de inasistencia, pacientes nuevos y cancelaciones contra el periodo anterior, gráfico de citas por día y servicios más agendados.

**Reglas.**
- **Tasa de inasistencia** = no asistió ÷ (atendidas + no asistió). Las canceladas no cuentan: no se esperaba a nadie.
- Los días se agrupan en la hora de la clínica.
- En los indicadores, la flecha es verde o roja según si el cambio es **bueno o malo**, no según si sube o baja (más inasistencia = rojo).

**Código.** API: `DashboardController` (`GET /dashboard/stats`). Web: `app/app/TodayDashboard.tsx`, `ActivitySection.tsx`, `components/charts/*`.

---

## Agenda

**Qué hace.** Vista de **día** (una columna por profesional), **semana** y **mes**; mini calendario para saltar a cualquier fecha; crear, editar, reprogramar y cambiar el estado de citas.

**Reglas.**
- **Estados** y transiciones permitidas (`app/Enums/AppointmentStatus.php`):
  `Programada → Confirmada → En sala → En atención → Atendida`, y desde las primeras también `Cancelada` (exige motivo) o `No asistió`. El panel solo muestra los botones válidos para el estado actual.
- Solo se pueden editar o reprogramar citas *Programadas*, *Confirmadas* o *En sala*.
- **No se pueden cruzar citas** del mismo profesional (configurable en Configuración → Clínica), ni agendar en horas fuera del horario ni dentro de un bloqueo. La API valida; el frontend muestra su mensaje.
- **Horarios libres sugeridos**: al crear una cita se muestran los espacios realmente libres del profesional ese día (`/appointments/availability`), sin horas ya pasadas.
- Cita **sin hora fija**: se atiende por orden de llegada; aparece arriba de la grilla.
- Cada cambio de estado queda en el historial de la cita (quién, cuándo y por qué canal: personal, WhatsApp, online).
- Desde la ficha de una mascota, "Agendar cita" abre la agenda con tutor y mascota ya elegidos.

**Código.** API: `AppointmentController`, `Actions/Agenda/{ScheduleAppointment, ChangeAppointmentStatus, AvailableSlots}`. Web: `app/app/agenda/*` (`TimeGrid` día/semana, `MonthGrid`, `MiniCalendar`, `AppointmentFormModal`, `AppointmentDetailPanel`).

---

## Tutores y mascotas

**Qué hace.** Búsqueda, ficha del tutor, ficha de la mascota, foto, alertas.

**Reglas.**
- **Búsqueda** por nombre (tolera errores de escritura), documento, celular, nombre de mascota o microchip. El buscador del encabezado (`Ctrl K`) lleva a esta lista.
- **Habeas Data (Ley 1581):** al registrar un tutor la **autorización de tratamiento de datos es obligatoria**, y cada canal de contacto (WhatsApp, SMS, correo) es una aceptación aparte. Cada cambio de autorización se **agrega** a un historial (nunca se edita): fecha, usuario y origen. **Nada se envía por un canal sin autorización vigente.**
- Celulares guardados en formato internacional E.164 (`+573001112233`); en pantalla se escriben y leen en formato local.
- El tutor es **por clínica** (privacidad): las clínicas no comparten tutores aunque sea la misma persona.
- **Alertas** de la mascota (alergias, comportamiento, condiciones crónicas): siempre visibles en la ficha, en la agenda, en el formulario de historia clínica y en el PDF. Se desactivan, no se borran.
- **Edad aproximada**: si no saben la fecha de nacimiento, se registra "2 años" y se guarda una fecha estimada (se muestra con ≈).
- **Fallecimiento**: la mascota deja de aparecer al agendar y en recordatorios; su historia se conserva.
- **Foto**: se sube o cambia en tres lugares: debajo de la imagen en la ficha ("Subir foto"), en "Editar" / "Nueva mascota" (al crear se elige y se sube al guardar) y en el diseñador del carné. Se re-codifica en el servidor (sin metadatos GPS), se recorta en cuadrado al mostrarse y solo la ven usuarios de la clínica (y la página pública del QR). Componente: `components/domain/PetPhotoUploader.tsx`.
- Un tutor con mascotas no se puede eliminar.

**Código.** API: `OwnerController`, `PetController`, `Actions/Patients/*`. Web: `app/app/tutores/*`, `app/app/mascotas/*`.

---

## Historia clínica

**Qué hace.** Línea de tiempo por mascota con 7 tipos de registro, adjuntos y PDF.

**Tipos.** Consulta (motivo, anamnesis, signos vitales, examen físico por sistema, diagnósticos, plan), Vacunación (catálogo por especie, lote, vencimiento, dosis), Desparasitación, Fórmula médica (varios medicamentos), Cirugía/procedimiento, Seguimiento y Nota clínica.

**Reglas.**
- **Ventana de edición** (24 h por defecto, configurable 12–72 h): el autor corrige su registro dentro de ese tiempo; después queda **cerrado** (candado). Solo un administrador puede editar registros cerrados o de otros.
- **Nunca se borra un registro**: se **anula** con motivo (mínimo 5 caracteres), queda tachado y visible, con quién y cuándo lo anuló.
- **Próximo control**: al registrar una vacuna del catálogo, si no se indica fecha se calcula sola con el refuerzo de esa vacuna (p. ej. triple felina → 1 año). Es la base de los recordatorios de la Fase 4.
- El **peso** de los signos vitales actualiza el peso de la mascota (solo si el registro es el más reciente).
- Al registrar se elige la **sede** donde se atendió (cuando la clínica tiene varias).
- **Adjuntos**: PDF, imágenes, Word, Excel (máx. 20 MB), por categoría (laboratorio, imagenología…). Eliminar un adjunto lo oculta, pero el archivo se conserva para la auditoría.
- **PDF**: historia completa (toda, últimos 12 meses o por fechas; respeta el filtro de tipo; con o sin anulados) o un registro suelto ("Imprimir fórmula" lleva línea de firma). Membrete con logo, razón social, NIT y la **tarjeta profesional** del veterinario. Cada descarga queda en la auditoría.

**Código.** API: `ClinicalRecordController`, `Actions/Clinical/SaveClinicalRecord`, `app/Clinical/*` (un archivo por tipo en `Types/`; agregar un tipo = una clase + su tabla), `ClinicalPdf` y `resources/views/pdf/*`. Web: `app/app/mascotas/[id]/{ClinicalHistory, RecordFormPanel}.tsx`, `lib/clinical.ts`.

---

## Configuración

Las secciones visibles dependen del rol.

| Sección | Qué se configura | Reglas |
|---|---|---|
| **Clínica** | Logo, nombre, razón social, NIT, contacto; reglas: ventana de edición, evitar cruces, duración por defecto | Solo administrador. Los datos legales salen en el membrete de los PDF (y luego en facturas). |
| **Sedes** | Nombre, dirección, teléfono, activa | Una sede inactiva no aparece al agendar; su historial se conserva. |
| **Equipo** | Rol, sedes, aparece en agenda y su color, quitar acceso; invitar; invitaciones pendientes | Quitar acceso conserva los registros clínicos con su nombre. Nadie puede cambiar su propio rol ni quitarse el acceso. |
| **Horarios** | Horario semanal por sede y, opcional, por profesional | Un profesional sin horario propio sigue el de la sede. Dos franjas = almuerzo. Fuera del horario no se agenda. |
| **Servicios** | Nombre, categoría, duración, precio, color, activo | La duración define cuánto ocupa la cita. Un servicio inactivo no se ofrece pero las citas pasadas lo conservan. |
| **Bloqueos** | Festivos, vacaciones, capacitaciones | Por clínica, sede o profesional; días completos u horas. No cancela citas ya agendadas. |
| **Mi perfil** | Nombre, celular, tarjeta profesional, contraseña | Los datos de la persona son los mismos en todas sus clínicas. Cambiar la contraseña cierra las otras sesiones. |

**Código.** API: `ClinicController`, `ProfileController`, `BranchController`, `MemberController`, `AgendaSettingsController`. Web: `app/app/configuracion/sections/*`.

---

## Recordatorios por WhatsApp

**Qué hace (4a, manual).** En el inicio, la tarjeta **"Para recordar"** lista las citas de mañana (programadas o confirmadas) y las vacunas, desparasitaciones y controles que vencen en los próximos 7 días. El botón **Recordar** abre WhatsApp (el de la clínica, en ese computador) con el mensaje ya escrito; la persona solo pulsa enviar. También está en el panel de cada cita.

**Reglas.**
- **El mensaje lo escribe la API** (`app/Messaging/ReminderTexts.php`), siempre igual: saludo con el primer nombre, nombre de la clínica (y la sede si hay varias), mascota, servicio, fecha y hora. Los automáticos (4c) usan las mismas variables; su plantilla termina en *"¿Nos confirmas tu asistencia? Usa los botones de abajo"* porque lleva botones (4d).
- **Solo con autorización**: si el tutor no tiene celular, no autorizó WhatsApp o la mascota falleció, el botón aparece deshabilitado con el motivo. La API vuelve a verificarlo.
- **Un control ya resuelto no se recuerda**: si después de la vacuna hay otra **de la misma vacuna** (o, en desparasitaciones y controles, otro registro del mismo tipo), el control viejo deja de aparecer. Una vacuna distinta no lo resuelve.
- "ya tiene cita" avisa que la mascota ya tiene una cita futura.
- **Caso "Michi" (decidido 2026-10-02):** si la mascota ya tiene una cita futura agendada o confirmada, el recordatorio **automático** de vacuna o control **no se envía** (queda como *"La mascota ya tiene una cita agendada"*): ya le llegará el de la cita. Si esa cita se cancela antes, sí sale.
- **Todo queda en la bitácora `messages`** (quién, cuándo, a qué número, el texto), y la tarjeta muestra "Recordado hace 2 h". Como el envío real lo hace la persona en WhatsApp, el estado es `opened` (se abrió), no "entregado".

**Automáticos (4c).** Según las reglas de la clínica (por defecto 24 h antes de cada cita y 7 días antes de cada control), solo entre 8:00 y 19:00, solo con autorización y dentro del límite del plan (Básico: solo manuales; Pro: 500 al mes). En **Configuración → Recordatorios** se activan, se cambia la anticipación y el horario, y se ven los **programados, enviados y no enviados** (con el motivo). En la tarjeta "Para recordar" aparece "Automático: vie 2:23 p. m." para no duplicar a mano. Cada tutor tiene en su ficha la tarjeta **"Mensajes"** con todo lo que se le ha enviado. Mientras WhatsApp no esté conectado, todo funciona en **modo de prueba** (nada sale a los celulares). Detalle técnico y conexión de Meta: [`whatsapp.md`](whatsapp.md).

**Código.** API: `ReminderController` (`GET /reminders/due`, `POST /reminders/manual`), `ReminderSettingsController`, `Messaging/ReminderTexts`, `Messaging/Reminders/*`, `Messaging/WhatsApp/*`, `Jobs/SendReminder`, comandos `reminders:dispatch` y `reminders:replan`, modelos `Message`, `Reminder`, `ReminderRule`, `UsageCounter`. Web: `app/app/RemindersCard.tsx`, `components/domain/WhatsAppReminderButton.tsx`, `configuracion/sections/RemindersSection.tsx`, `tutores/[id]/MessagesCard.tsx`.

---

## Documentos y firmas

**Qué hace.** Consentimientos y autorizaciones que se llenan solos y se firman en la pantalla: eutanasia, cirugía y anestesia, hospitalización y alta voluntaria. Desde la ficha de la mascota → tarjeta **Documentos**.

**Reglas.**
- El tutor debe tener documento de identidad y el veterinario responsable tarjeta profesional; si no, no se puede crear.
- El texto se congela al crear; la **primera firma lo bloquea**; con la última queda el PDF firmado (una sola vez) con hoja de evidencias.
- El veterinario firma desde su propia sesión; tutor y testigo en la sesión de quien atiende. Una firma por rol; se rechaza un recuadro vacío y firmar sin marcar "leí y acepto".
- Crear y recoger firmas: veterinarios y administradores. Ver: también recepción. Anular: quien lo creó, el veterinario responsable o un administrador. Nunca se borra.
- **A distancia:** enlace de un solo uso (48 h) por WhatsApp de la clínica, correo o copiado; el tutor confirma los últimos 4 dígitos de su documento (5 intentos). En el portal firma sin dígitos y ve sus documentos firmados. Enviar el enlace bloquea el texto.
- **Textos propios (D4):** Configuración → Documentos (administradores) edita el texto por tipo; solo el texto, no los campos ni los firmantes; aplica a documentos nuevos.
- Los textos base **no están revisados por un abogado** todavía.

**Código.** API: `PetDocumentController`, `Documents/*`, `Actions/Documents/SignPetDocument`, tablas `pet_documents` y `pet_document_signatures`. Web: `mascotas/[id]/DocumentsCard.tsx`, `DocumentFormPanel.tsx`, `app/app/documentos/[id]/`, `components/domain/SignaturePad.tsx`. Detalle: [`documentos.md`](documentos.md).

---

## Carné digital con QR

**Qué hace (4b).** En la ficha de cada mascota, la tarjeta **"Carné digital"**: el QR, **Diseñar e imprimir carné**, la página pública, el **modo mascota perdida** y los últimos escaneos.

**Diseñador de carné** (`/app/mascotas/{id}/carne`): vista previa en vivo del frente y el reverso, a tamaño de tarjeta de identificación (8,6 × 5,4 cm).
- **Plantillas:** *Clásico* (tipo cédula, fondo de seguridad), *Moderno* (degradado, foto redonda) y *Huellitas* (alegre, huellas de fondo).
- **Color:** 10 colores o uno propio. Si el color es claro, el texto se vuelve oscuro solo (contraste calculado).
- **Mostrar:** foto (sin foto se ve una huella), vacunas en el reverso, celular del tutor ("Si me encuentras…"; apagado por defecto: el tutor decide si su número va impreso).
- **Guardar diseño de este carné** (por mascota, para que la reimpresión salga igual) y, para administradores, **usar como diseño de la clínica** (el que tienen por defecto todas las mascotas).
- **Descargar imagen:** un PNG con frente y reverso a 4× resolución, para WhatsApp o una papelería.
- **Imprimir:** hoja carta con frente y reverso a tamaño real, marcas de corte y línea de doblez; se recorta, se dobla y se plastifica. `?vista=1` muestra la hoja sin abrir el diálogo de impresión.
- El carné lo dibuja el navegador (`components/pet-card/PetCard.tsx`) en milímetros reales (`1em = 1mm`): lo mismo que se ve es lo que se descarga y se imprime.
- **El mismo carné aparece en el portal del tutor** (ver abajo): ahí se voltea con una animación 3D al tocarlo y se descarga igual. Los datos los arma `app/Pets/PetCard.php` para los dos.

**La página del QR** (`/m/{token}`, pública, sin login, pensada para celular) muestra **solo**: foto, nombre, especie, raza, sexo, edad, si tiene microchip (no el número), la clínica que la respalda y el **estado de cada vacuna** (vigente hasta…, vencida, sin refuerzo). Sirve para guarderías, peluquerías, hoteles, conjuntos y viajes.

**Reglas.**
- **Nunca** muestra documento ni dirección del tutor, número de microchip ni la historia clínica. El teléfono del tutor solo aparece en modo perdida.
- **Estado de vacunas**: la última aplicación de **cada** vacuna; "vigente" si su próximo refuerzo no ha pasado. Lo calcula `app/Pets/VaccinationStatus.php` (el mismo cálculo para el carné, la página del QR y el portal).
- **Orden de las vacunas: rabia primero, luego la más reciente** (antes era alfabético y podía esconder la de rabia). En el reverso del carné caben unas pocas: si hay más, la última fila dice **"+N vacunas más · escanea el QR"** (nada desaparece en silencio); la página del QR las muestra todas.
- **Modo mascota perdida**: la página muestra un aviso ("¡Hola! Soy Kira y me perdí") con botones para llamar o escribir por WhatsApp al tutor (o a otro número que se indique). Se activa y desactiva desde la ficha y queda en la auditoría. Mientras está activo, la ficha se resalta y revisa los escaneos cada 30 s.
- **Página pública activa**: se puede apagar; en modo perdida se muestra igual.
- **Generar un QR nuevo** invalida los carnés impresos (si se perdió o se usó mal).
- **Escaneos**: uno por visitante cada 10 min (recargar no suma); al activar el modo perdida el siguiente escaneo siempre cuenta. No se guarda la IP, solo un hash. El servidor de Next reenvía la IP del visitante (`X-Forwarded-For`); Laravel solo la acepta de los proxies de `TRUSTED_PROXIES`.
- La página no se indexa en buscadores (`noindex`) y tiene límite de 30 consultas por minuto por IP.

**Código.** API: `PublicPetController` (público), `PetCardController` (datos y diseño del carné, QR, modo perdida, escaneos), `Pets/CardDesign`, `Pets/VaccinationStatus`, tabla `pet_public_views`, columna `pets.card_design`. Web: `components/pet-card/*` (plantillas), `app/app/mascotas/[id]/carne/` (diseñador), `app/imprimir/carne/[id]` (hoja de impresión), `app/m/[token]/page.tsx` (página pública), `app/api/public/pet-photo/[token]`, `app/app/mascotas/[id]/PetCardSection.tsx`.

---

## Bot de WhatsApp y bandeja

**Qué hace (4d).** El recordatorio automático de cita llega con tres botones: **Confirmar**, **Reprogramar** y **Cancelar**. El bot (sin IA) responde solo:

| El tutor… | El bot… |
|---|---|
| toca **Confirmar** | confirma la cita (queda en el historial con canal WhatsApp) y agradece |
| toca **Cancelar** | pregunta "¿Seguro?" y, si confirma, cancela con motivo "Cancelada por el tutor por WhatsApp" |
| toca **Reprogramar** | ofrece **3 horarios libres reales** (días distintos, mismo profesional y duración); al elegir uno verifica que siga libre y mueve la cita |
| escribe texto libre | **con la bandeja en su plan:** lo pasa a la bandeja del equipo y responde una vez "te responderán pronto". **Sin ella:** responde una vez con el WhatsApp propio de la clínica (`wa.me/…`) |

**Bandeja WhatsApp** (`/app/whatsapp`, menú lateral con contador; solo planes con `whatsapp_inbox`): pestañas "Por responder" y "Todas", el chat con burbujas como en el celular y respuesta del equipo. **Regla de Meta:** solo se puede escribir texto libre dentro de las 24 h siguientes al último mensaje del tutor; la pantalla lo indica y bloquea el envío.

**Simulador (mientras Meta no esté conectado):** en la ficha del tutor → *Mensajes* → abrir un recordatorio → "Simular respuesta del tutor", o en la bandeja tocar los botones / "Escribir como el tutor". Usa el mismo formato que mandará Meta.

**Reglas.** Número compartido: cada botón trae el id del mensaje, así se sabe de qué clínica y cita es. El webhook solo acepta notificaciones firmadas por Meta y procesa cada mensaje una sola vez. Detalle: [`whatsapp.md`](whatsapp.md). Costos y planes: [`planes.md`](planes.md).

**Código.** API: `WhatsAppWebhookController`, `ConversationController`, `Messaging/Bot/*` (`WebhookProcessor`, `WhatsAppBot`, `WhatsAppReplier`), `Jobs/ProcessWhatsAppWebhook`, tabla `whatsapp_conversations`. Web: `app/app/whatsapp/`, `lib/conversations.ts`, simulador en `tutores/[id]/MessagesCard.tsx`.

---

## Portal del tutor

**Qué hace (4e).** El dueño de la mascota entra desde su celular a **`/portal`** y ve, de **todas** las clínicas Zooetia que lo invitaron: sus mascotas, el **carné con QR** (el mismo diseño de la clínica; se voltea al tocarlo y se descarga), las **vacunas** y las **próximas citas**, con botón para llamar a la clínica.

**Cómo entra.** La clínica lo invita desde la ficha del tutor (tarjeta **"Portal del tutor"**: por su propio WhatsApp con el texto listo, por correo o copiando el enlace). El tutor abre el enlace **una sola vez**, crea correo y contraseña, y queda con sesión de 180 días. **No hay códigos para entrar.** Si otra clínica lo invita (mismo celular), se suma a la misma cuenta con un toque. ¿Olvidó la contraseña? `/portal/recuperar`: código de 6 dígitos por correo.

**Reglas.** El enlace es de un solo uso y vence en 7 días. Lo que ve lo decide el registro de la clínica (nunca datos que escriba el tutor). Las sesiones del portal y del personal nunca se cruzan. La clínica ve en la ficha si el tutor lo activó y su último ingreso. Detalle y seguridad: [`portal.md`](portal.md).

**Código.** API: `Portal/PortalAuthController`, `Portal/PortalPetController`, `PortalInvitationController`, `Actions/Portal/*`, `Portal/TutorPortal`. Web: `app/portal/*`, `app/api/tutor*`, `tutores/[id]/PortalCard.tsx`.

---

## Reserva de citas en línea y notificaciones

La clínica publica su enlace (`/reservar/{clínica}`, QR y cartel); la persona elige servicio, día y hora y deja sus datos con documento. La reserva es una **solicitud** que aparta la hora; la clínica la acepta o la rechaza en **Agenda → Solicitudes**, y el tutor recibe cada paso por WhatsApp. Los avisos al equipo llegan a la **campana** sin refrescar. Detalle: [`reservas.md`](reservas.md) y [`notificaciones.md`](notificaciones.md).

---

## Inventario, ventas y reportes (Fase 5a)

- **Inventario**: productos, existencias por sede, compras, ajustes, traslados, conteo físico, alertas de stock bajo y vencimientos, importación desde Excel.
- **Ventas**: venta a un tutor o de mostrador, varios medios de pago y abonos, comprobante PDF (no es factura electrónica), anulaciones con motivo; cobro desde la cita y productos usados en la historia clínica.
- **Reportes**: cierre del día, ventas del periodo y exportación a Excel para el contador; "Recaudado hoy" en el inicio.

Reglas, permisos y código: [`inventario.md`](inventario.md).

---

## Tareas

**Qué hace.** Tareas internas asignadas a personas (llamar a un tutor, revisar un resultado), opcionalmente ligadas a una mascota o tutor.

**Estado.** ✅ Completo (Fase 4f). API: `TaskController` (`tests/Feature/TaskTest.php`). Pantalla `/app/tareas` (`web/src/app/app/tareas/`):
- Pestañas **Mías / Creadas por mí / Toda la clínica** y filtro **Pendientes / Hechas / Descartadas**.
- Las pendientes se agrupan en **Vencidas** (en rojo), **Hoy**, **Próximas** y **Sin fecha**.
- Casilla para completar con un clic (y reabrir); panel lateral para crear o editar: responsable, fecha y hora límite (sin hora = vence al final del día), tutor y mascota (el mismo buscador de la agenda, `components/domain/OwnerPetPicker.tsx`).
- Solo quien la creó, su responsable o un administrador puede modificarla; el resto la ve en solo lectura.
- El inicio muestra "Mis tareas" con enlace a la pantalla.
