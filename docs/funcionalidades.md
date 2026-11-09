# Catálogo de funcionalidades de Zooetia

Todo lo que hace Zooetia hoy, en un solo lugar, para presentarlo al equipo (producto, ventas, soporte y desarrollo). Cada fila dice **qué es**, **dónde está** en la aplicación y **qué plan la necesita**. Las reglas de negocio de cada módulo están en [`modulos.md`](modulos.md); cómo se llegó hasta aquí, en [`historial.md`](historial.md).

*Actualizado: 2026-10-03 (Fases 1 a 4 terminadas, falta conectar Meta; Documentos y firmas D1–D2).*

**Leyenda de planes** (provisional, ver [`planes.md`](planes.md)): **Todos** = cualquier plan · **Pro** = incluye `whatsapp_bot` · **Superior** = incluye `whatsapp_inbox`. Durante la prueba gratis de 15 días la clínica tiene las funciones Pro.

---

## 0. Página pública (landing)

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Página de inicio** de Zooetia: qué es, funciones, WhatsApp, consentimientos, carné y portal, cómo empezar, **precios** (salen de la base de datos, los mismos de Plan y facturación), preguntas frecuentes; enlaces a registro, ingreso, portal del tutor y textos legales. Si ya hay sesión, muestra "Ir a mi clínica" | `/` | — |

## 1. Cuenta, clínicas y equipo

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Registro de una clínica nueva** con **prueba gratis de 15 días sin tarjeta**; acepta términos y política de datos (queda registrado cuándo y qué versión) | `/registro` (enlace desde el login) | Todos |
| **Primeros pasos**: tarjeta en el inicio que se marca sola (datos de la clínica, logo, horario, servicios, equipo, primer tutor) | Inicio (administradores) | Todos |
| Términos de uso y política de tratamiento de datos (texto base, pendiente de revisión legal) | `/terminos`, `/privacidad` | — |
| Ingreso con correo y contraseña; sesión segura (el navegador nunca ve el token) | `/login` | Todos |
| **Una persona en varias clínicas**: elige cuál abrir y cambia desde su menú | `/seleccionar-clinica`, menú del usuario | Todos |
| **Roles**: Administrador, Veterinario, Recepción (cada uno ve y hace solo lo suyo) | Configuración → Equipo | Todos |
| **Invitar al equipo** por correo (enlace de 7 días; si ya tiene cuenta, solo acepta) | Configuración → Equipo | Todos |
| Desactivar a un miembro (pierde el acceso al instante) | Configuración → Equipo | Todos |
| Prueba o suscripción vencida: **todo visible, nada editable** (no se pierden datos); aviso "Modo consulta · Activar plan" en la barra superior | Toda la app | — |
| **Plan y facturación**: estado del plan, uso del mes, comparación de planes y solicitud de plan (pago con Mercado Pago pendiente). Plan pagado vencido: 5 días de gracia | Configuración → Plan y facturación | Todos |
| **Mi perfil**: nombre, celular, tarjeta profesional, cambio de contraseña (cierra otras sesiones) | Configuración → Mi perfil | Todos |

## 2. Configuración de la clínica

| Funcionalidad | Dónde | Plan |
|---|---|---|
| Datos de la clínica: nombre, razón social, NIT, contacto, **logo** (sale en carnés y PDF) | Configuración → Clínica | Todos |
| Ajustes: ventana de edición de la historia (12/24/48/72 h), evitar cruces de citas, duración por defecto | Configuración → Clínica | Todos |
| **Sedes** (varias por clínica) | Configuración → Sedes | Todos |
| **Horarios** por sede y por profesional | Configuración → Horarios | Todos |
| **Servicios** con duración, categoría, precio y color | Configuración → Servicios | Todos |
| **Bloqueos** de agenda (vacaciones, capacitaciones) | Configuración → Bloqueos | Todos |
| **Recordatorios automáticos**: activar, anticipación, horario de envío, ver programados/enviados/no enviados con el motivo | Configuración → Recordatorios | Pro |
| **Diseño de carné de la clínica** (el que tienen por defecto todas las mascotas) | Diseñador de carné | Todos |
| **Texto de los consentimientos** (propio de la clínica) | Configuración → Documentos | Todos |

## 3. Inicio: "Hoy en la clínica"

| Funcionalidad | Dónde | Plan |
|---|---|---|
| Contadores del día: citas, por confirmar, en sala, atendidas | `/app` | Todos |
| Agenda del día y **sala de espera** | `/app` | Todos |
| **Mis tareas** pendientes, con enlace a la pantalla de Tareas | `/app` | Todos |
| **Para recordar**: citas de mañana y vacunas/controles de la semana, con botón "Recordar" | `/app` | Todos (automático: Pro) |
| **Actividad** 7/30/90 días: atendidas, inasistencia, pacientes nuevos, cancelaciones, citas por día, servicios más agendados | `/app` | Todos |

## 4. Agenda

| Funcionalidad | Dónde | Plan |
|---|---|---|
| Vista de **día** (una columna por profesional), **semana** y **mes**; mini calendario | `/app/agenda` | Todos |
| Crear, editar y **reprogramar** citas; cita **sin hora fija** (por orden de llegada) | `/app/agenda` | Todos |
| **Horarios libres sugeridos** al crear una cita | Formulario de cita | Todos |
| **Estados**: Programada → Confirmada → En sala → En atención → Atendida; Cancelada (con motivo); No asistió | Panel de la cita | Todos |
| Historial de cada cita: quién cambió qué y por qué canal (personal, WhatsApp) | Panel de la cita | Todos |
| No se cruzan citas, no se agenda fuera de horario ni en bloqueos | Toda la agenda | Todos |
| **Recordar** una cita por WhatsApp desde su panel | Panel de la cita | Todos |

## 4b. Reserva de citas en línea

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Página pública de reservas** de cada clínica: servicio → día y hora libres → datos con **documento** y WhatsApp, y autorizaciones (Ley 1581 y WhatsApp) | `/reservar/{clínica}` | Pro |
| La reserva es una **solicitud** que aparta la hora; **la clínica siempre la acepta o la rechaza** | Agenda → Solicitudes | Pro |
| Al aceptar, **sugiere el tutor y la mascota** por documento o celular (sin duplicados por documento; ofrece actualizar el celular si cambió); si no existe, los crea con sus autorizaciones | Agenda → Solicitudes | Pro |
| Solicitudes punteadas en violeta en la agenda, botón "Solicitudes en línea (N)" e insignia en el menú | Agenda | Pro |
| Configuración: activar, enlace para compartir, anticipación, días adelante, elegir profesional, mensaje | Configuración → Reserva en línea | Pro |
| **WhatsApp al tutor**: solicitud recibida (con "Cancelar solicitud"), aceptada (con Reprogramar / Cancelar) y rechazada (con motivo y enlace) | Automático | Pro |
| **Pedir una cita desde el portal del tutor**, con sus datos y mascotas ya llenos | Portal → Pedir una cita / carné de la mascota | Pro |
| **QR del enlace de reservas** (descargar) y **cartel para el mostrador** listo para imprimir | Configuración → Reserva en línea | Pro |
| **Campana de notificaciones en vivo**: sin refrescar (cada 20 s y al volver a la pestaña), aviso emergente, sonido, número en la pestaña, avisos de escritorio opcionales. Avisa solicitudes nuevas y cancelaciones/reprogramaciones por WhatsApp | Barra superior | Todos |

## 5. Tutores y mascotas

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Búsqueda** por nombre (tolera errores), documento, celular, mascota o microchip; `Ctrl K` desde cualquier pantalla | `/app/tutores` | Todos |
| Ficha del tutor: contacto, botones WhatsApp/Llamar, mascotas | `/app/tutores/{id}` | Todos |
| **Autorizaciones Habeas Data (Ley 1581)**: tratamiento de datos obligatorio; WhatsApp, SMS, correo y promociones por separado; historial con fecha y usuario | Ficha del tutor | Todos |
| **Mensajes**: todo lo que se le ha enviado al tutor y sus respuestas | Ficha del tutor | Todos |
| **Portal del tutor**: estado (activo / invitado) e invitar por WhatsApp, correo o enlace | Ficha del tutor | Todos |
| Ficha de la mascota: datos, **foto**, edad calculada, peso, temperamento, microchip | `/app/mascotas/{id}` | Todos |
| **Alertas** (alergias, comportamiento, condiciones), visibles en agenda e historia | Ficha de la mascota | Todos |
| Próximas citas de la mascota y "Agendar cita" con la mascota ya elegida | Ficha de la mascota | Todos |

## 6. Historia clínica

| Funcionalidad | Dónde | Plan |
|---|---|---|
| Línea de tiempo con filtros por tipo | Ficha de la mascota | Todos |
| Registros: **consulta (SOIP)**, vacunación, desparasitación, fórmula, procedimiento, seguimiento, nota | "Nuevo registro" | Todos |
| **Próximo control** en vacunas, desparasitaciones y controles (alimenta los recordatorios) | Formulario del registro | Todos |
| **Ventana de edición**: el autor corrige dentro del plazo; después queda cerrado. **Nunca se borra: se anula** con motivo | Cada registro | Todos |
| Adjuntos (resultados, imágenes) | Cada registro | Todos |
| **PDF de la historia** (completa, por periodo y tipo, con o sin anulados) y **PDF de un registro**; estilo sobrio para imprimir en blanco y negro | "Descargar PDF" | Todos |

## 6b. Documentos y firmas (consentimientos)

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Consentimientos que se llenan solos**: eutanasia, cirugía y anestesia, hospitalización, alta voluntaria | Ficha de la mascota → Documentos → + Nuevo | Todos |
| Solo se llenan los datos del caso (diagnóstico, procedimiento, destino del cuerpo, costos…); tutor, mascota, veterinario y clínica salen solos | Panel "Nuevo documento" | Todos |
| **Firma en pantalla** del tutor, el veterinario (desde su sesión) y un testigo opcional, con casilla "leí y acepto" | `/app/documentos/{id}` | Todos |
| El texto se **bloquea** con la primera firma; al completar las firmas queda el **PDF firmado** con hoja de **evidencias** (huella SHA-256, fecha y hora, dispositivo, IP) | Documento | Todos |
| Borrador con marca "BORRADOR" para leerlo con el tutor; **anular** con motivo (nunca se borra) | Documento | Todos |
| **Firma a distancia**: enlace de un solo uso por WhatsApp de la clínica, correo o copiado; el tutor confirma los últimos 4 dígitos de su documento y firma desde el celular | Documento → "enviar para firmar a distancia"; `/firmar/{enlace}` | Todos |
| **Textos propios de la clínica**: editar párrafos y viñetas, insertar datos automáticos con un clic, párrafos condicionales, vista previa en vivo, restaurar el texto de Zooetia | Configuración → Documentos | Todos |
| En el **portal**: "Tienes un documento por firmar" (sin dígitos: la sesión lo identifica) y "Documentos firmados" con el PDF | `/portal` | Todos |

## 7. Carné digital con QR

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Diseñador**: 3 plantillas (Clásico, Moderno, Huellitas), 10 colores o uno propio, foto, vacunas, celular del tutor opcional | `/app/mascotas/{id}/carne` | Todos |
| **Descargar** PNG (frente y reverso) e **imprimir** a tamaño real con marcas de corte | Diseñador | Todos |
| **Página del QR** (pública, sin login): mascota, clínica y **estado de cada vacuna**; nunca datos privados | `/m/{código}` | Todos |
| Vacunas por importancia (**rabia primero**) y "+N vacunas más · escanea el QR" si no caben | Carné | Todos |
| **Modo mascota perdida**: la página del QR muestra un aviso con botones para llamar o escribir al tutor | Ficha de la mascota | Todos |
| Contador de **escaneos**; apagar la página pública; **generar un QR nuevo** (invalida el impreso) | Ficha de la mascota | Todos |

## 8. WhatsApp

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Recordatorio manual**: abre el WhatsApp de la clínica con el mensaje ya escrito (gratis) | Inicio, panel de cita | Todos |
| **Recordatorios automáticos** de citas (24 h antes) y vacunas/controles (7 días antes), entre 8:00 y 19:00, con límite mensual | Automático | Pro (500/mes) |
| No recuerda lo ya resuelto (vacuna aplicada, mascota fallecida, sin autorización) ni el control si **ya tiene cita** | Automático | Pro |
| **Bot de botones**: el tutor confirma, cancela o **reprograma eligiendo entre 3 horarios libres reales** | WhatsApp del tutor | Pro |
| Texto libre sin bandeja: respuesta única con el WhatsApp propio de la clínica | WhatsApp del tutor | Pro |
| **Bandeja WhatsApp**: el equipo conversa con el tutor (ventana de 24 h de Meta), con contador en el menú | `/app/whatsapp` | Superior |
| **Simulador** para probar todo sin Meta (mientras no esté conectado) | Ficha del tutor, bandeja | Desarrollo |

## 9. Portal del tutor

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Acceso por invitación de la clínica**, una sola vez; sesión de 180 días; **sin códigos para entrar** | `/portal/activar/…`, `/portal/ingresar` | Todos |
| Una cuenta para **todas las clínicas** que lo invitaron | `/portal` | Todos |
| Sus mascotas con próxima cita y vacunas vencidas | `/portal` | Todos |
| **Carné** con el diseño de la clínica: se **voltea con animación 3D**, se descarga | `/portal/mascotas/{id}` | Todos |
| Vacunas, próximas citas y botón para llamar a la clínica | `/portal/mascotas/{id}` | Todos |
| **Recuperar contraseña** con código de 6 dígitos por correo | `/portal/recuperar` | Todos |

## 10. Tareas

| Funcionalidad | Dónde | Plan |
|---|---|---|
| Tareas del equipo: **Mías / Creadas por mí / Toda la clínica**; pendientes, hechas, descartadas | `/app/tareas` | Todos |
| Agrupadas por **Vencidas, Hoy, Próximas, Sin fecha** | `/app/tareas` | Todos |
| Crear con responsable, fecha y hora límite, tutor y mascota; **completar con un clic**; reabrir; descartar | `/app/tareas` | Todos |

## 10b. Inventario (Fase 5a · V1)

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Productos**: tipo, unidad, precio con IVA incluido, IVA, costo (solo administrador), código, existencia mínima; sin duplicados | Inventario | Todos |
| **Existencias por sede** con estados: bajo, agotado, en negativo, no se maneja aquí | Inventario | Todos |
| **Entradas** (con costo, lote y vencimiento), **traslados** entre sedes y **ajustes** (conteo físico, daño, pérdida, vencido; solo administrador), con historial y saldo | Panel del producto | Todos |
| **Alertas**: existencias bajas y lotes por vencer (60 días), con "Dar de baja" o "Ya lo revisé"; aviso diario en la campana | Inventario → Alertas | Todos |
| **Importar productos desde Excel** (CSV), tolerante al formato de Excel en español; actualiza sin duplicar | Inventario → Importar | Todos |

## 10c. Ventas y pagos (Fase 5a · V2)

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Ventas** a un tutor o de mostrador, con consecutivo por clínica y datos del cliente congelados | Ventas · ficha del tutor | Todos |
| Productos (descuentan existencias), servicios y cargos libres; cantidad, precio y descuento por ítem o a toda la venta; IVA incluido | Venta | Todos |
| **Pagos** en efectivo (con cálculo del cambio), tarjeta, transferencia, Nequi, Daviplata; pagos parciales; pendiente → abonada → pagada | Venta | Todos |
| **Anular** pagos o ventas con motivo (solo administrador); los productos vuelven al inventario | Venta | Todos |
| **Comprobante PDF** "no es factura electrónica" | Venta → Comprobante | Todos |
| **Cuenta del tutor**: lo que debe y sus ventas | Ficha del tutor | Todos |
| **"Cobrar" desde la cita**: el servicio va a la cuenta del día del tutor; nunca se cobra dos veces | Agenda → panel de la cita | Todos |
| **Producto del inventario en la historia** (vacuna, desparasitación, procedimiento): llena nombre y lote, se carga a la cuenta del día o solo se descuenta si va incluido | Historia clínica → nuevo registro | Todos |

## 10d. Reportes (Fase 5a · V4)

| Funcionalidad | Dónde | Plan |
|---|---|---|
| **Cierre del día**: recaudado por medio de pago y por quien recibió, ventas, por cobrar y anulaciones; imprimible | Reportes | Todos |
| **"Recaudado hoy"** en el inicio | Inicio | Todos |
| **Ventas del periodo** comparadas con el periodo anterior: por día, lo más vendido, por tipo, medio de pago y vendedor | Reportes → Ventas | Pro |
| **Exportar a Excel** ventas (por ítem) y pagos, para el contador | Reportes → Ventas | Pro |

## 11. Seguridad y cumplimiento (transversal)

| Qué | Cómo |
|---|---|
| **Aislamiento entre clínicas** | Cada dato lleva su clínica; varias capas de protección (consulta, base de datos y pruebas automáticas) impiden ver datos de otra clínica. |
| **Habeas Data** | Autorizaciones separadas por canal, con historial; nada se envía sin autorización. |
| **Auditoría** | Quién creó, cambió, anuló o exportó (PDF) cada cosa. |
| **Historia clínica íntegra** | Ventana de edición, anulación con motivo, nunca borrado. |
| **Fotos** | Se re-codifican al subirlas (se eliminan datos de ubicación del celular). |
| **Sesiones** | Tokens en cookie segura; el personal y los tutores tienen sesiones separadas que nunca se cruzan. |
| **Límites de intentos** | Login, invitaciones, códigos, página del QR y webhook de WhatsApp. |

---

## Lo que viene

| Qué | Estado |
|---|---|
| **Conectar WhatsApp real (Meta / WABA)** | Código listo; falta el trámite. Paso a paso: [`whatsapp.md`](whatsapp.md) |
| **Pago en línea con Mercado Pago** (hoy: solicitud + activación manual) | Pendiente. Plan en [`cobro.md`](cobro.md) |
| **Definir planes y precios finales** | Insumos en [`planes.md`](planes.md) |
| Portal: fórmulas y documentos compartidos, modo perdida desde el portal | Pendiente |
| **Documentos**: **revisión legal** de las plantillas de Zooetia | Pendiente |
| Notificaciones en tiempo real con WebSockets (Laravel Reverb) | Decidido para cuando estemos en el servidor; ver [`notificaciones.md`](notificaciones.md) |
| **Fase 5b**: facturación electrónica DIAN | Después, con asesoría contable |
| App móvil del tutor (mismo API del portal) | Futuro |
