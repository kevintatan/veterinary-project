# Fase 4 — Diferenciadores

Plan aprobado el 2026-10-02. Rama: `fase-4-diferenciadores`. Este archivo se actualiza al terminar cada paso.

## Estrategia: todo listo antes de Meta

El WhatsApp automático usa la **WhatsApp Business Cloud API** de Meta, y su trámite (verificar la empresa, número, nombre "Zooetia", aprobar plantillas) es lo más lento de la fase. Por eso:

- Todo el envío pasa por una **interfaz de proveedor** (`WhatsAppProvider`). En desarrollo se usa un **conector simulado** que escribe cada mensaje en la bitácora y en el log, y que puede simular las respuestas del tutor (botones, texto).
- Los webhooks de Meta (estados de entrega y mensajes entrantes) se procesan con el **formato real de Meta**, probado con payloads de ejemplo en los tests.
- **Conectar la WABA = configurar credenciales y cambiar el conector a `meta`**. No hay que tocar la lógica. La guía paso a paso está en [`whatsapp.md`](whatsapp.md).

## Pasos

| Paso | Qué | Depende de Meta | Estado |
|---|---|---|---|
| **4a** | **Recordatorios manuales por WhatsApp**: botón que abre WhatsApp con el mensaje escrito (cita, vacuna, control); lista "Para recordar" en el inicio; registro de cada envío; respeta la autorización del canal. | No | ✅ 2026-10-02 |
| **4b** | **Carné digital con QR**: PDF del carné, página pública mínima `/m/{token}` con el estado de vacunas verificado, **modo mascota perdida**, regenerar QR, registro de escaneos. | No | ✅ 2026-10-02 |
| **4c** | **Motor de recordatorios automáticos**: reglas por clínica (p. ej. 24 h antes de la cita, 7 días antes del refuerzo), tarea programada + cola, idempotencia y reintentos, cancelación/recálculo al reprogramar, omitir sin consentimiento o mascota fallecida, bitácora de mensajes, consumo mensual por clínica. Con el conector simulado. | No (simulado) | ✅ 2026-10-02 |
| **4d** | **Bot de confirmación**: plantillas con botones Confirmar / Reprogramar / Cancelar; webhook de Meta; enrutamiento de respuestas con número compartido; reprogramar ofreciendo horarios libres; paso a humano con aviso en el dashboard. Con el conector simulado y un simulador de conversación en desarrollo. | No (simulado) | ✅ |
| **4e** | **Portal del tutor**: acceso por **invitación de la clínica** (verificación una sola vez, ver abajo), mascotas en todas las clínicas Zooetia, vacunas, próximas citas, carné con QR (ver, voltear, descargar). Sesión separada del personal. Guía: [`portal.md`](portal.md). *Fórmulas y documentos compartidos quedan para después (necesitan `visible_to_owner`).* | No | ✅ |
| **4f** | **Pantalla de Tareas** (`/app/tareas`): mías / creadas por mí / toda la clínica; pendientes agrupadas en vencidas, hoy, próximas y sin fecha; crear, asignar, ligar a tutor o mascota, completar con un clic, descartar y reabrir. | No | ✅ |
| **Conexión** | Configurar la WABA real, registrar el webhook, aprobar plantillas, prueba con números reales. | **Sí** | Cuando Meta apruebe |

## Acceso del tutor (decidido 2026-10-02, implementado en 4e)

Un código por WhatsApp en **cada** ingreso cuesta (Meta cobra cada mensaje *authentication*, y se multiplica por ingresos y tutores), carga el número compartido y aburre al usuario. En su lugar:

1. **La clínica invita** desde la ficha del tutor ("Invitar al portal"): enlace de activación de **un solo uso** (7 días) por WhatsApp (botón manual = gratis, o plantilla) o por correo. El enlace solo llega a quien la clínica ya tiene registrado con su celular y autorización: eso prueba la identidad. **Qué mascotas ve lo decide el registro de la clínica**, no datos que el tutor escriba (nombre o microchip de la mascota se adivinan o están en el carné: no sirven para validar).
2. Al activar crea su acceso **una vez** (correo + contraseña; en la app, huella / Face ID).
3. **Sesión larga** en cada dispositivo: no hay códigos para entrar.
4. **Código solo de respaldo** (dispositivo nuevo sin contraseña): primero por correo (gratis), WhatsApp como alternativa.
5. **Una cuenta, varias clínicas**: si otra clínica Zooetia invita el mismo celular, se suma a la cuenta existente con un toque.
6. **Sin invitación**: puede pedir acceso con su celular → **un** código, una sola vez; solo ve lo que las clínicas tienen con ese número.

Cambia `database.md` §10b: `tutor_accounts` con correo y contraseña (o passkey) y una tabla de invitaciones de un solo uso; `tutor_login_codes` queda solo para el respaldo.

## Fuera de esta fase

- **Reserva online pública** (`/reservar/[clinica]`): después del bot, porque reutiliza su motor de horarios y confirmaciones. *(Ya construida: ver [`reservas.md`](reservas.md).)*
- **Inventario, ingresos y facturación electrónica**: Fase 5. *(5a inventario y ventas ya construida: ver [`inventario.md`](inventario.md); 5b factura DIAN pendiente.)*
- **Redis + Horizon**: al montar el servidor de staging; en local la cola usa la base de datos.

## Trámite de Meta (en paralelo, lo hace el equipo)

Resumen; detalle en [`whatsapp.md`](whatsapp.md) §4.

1. Cuenta de **Meta Business** (business.facebook.com) a nombre de la empresa.
2. **Verificación de la empresa** (pide documentos: RUT o Cámara de Comercio, dominio o teléfono de la empresa).
3. **App en developers.facebook.com** con el producto WhatsApp → crea la WABA y da un número de prueba (sirve para 5 números registrados).
4. **Número definitivo**: una línea que **no** esté registrada en la app de WhatsApp. Nombre visible "Zooetia" (Meta lo revisa).
5. **Plantillas** (*utility*) enviadas a aprobación con los textos de `whatsapp.md` §3.
6. Token de sistema permanente (usuario del sistema en Business Manager) y secreto de la app para validar el webhook.

Sin empresa constituida todavía, se puede avanzar con el **número de prueba** de Meta hasta tener los documentos.
