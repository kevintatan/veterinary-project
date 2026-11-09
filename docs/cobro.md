# Registro de clínicas y cobro de la suscripción

> Estado al 2026-10-03: **registro web listo**, **pantalla de plan lista**, **activación manual lista**. **Pago en línea: pendiente** (se implementa después; pasarela por definir entre Mercado Pago y Wompi, ver §6).

## 1. Registro de una clínica (`/registro`)

- Enlace desde el login: "¿Tu clínica aún no usa Zooetia? Crea una cuenta gratis".
- Pide: nombre de la clínica, nombre de la persona, celular (opcional), correo y contraseña (mínimo 8, con letras y números, se repite).
- **Aceptar términos y política de datos es obligatorio** (casilla; el botón no se activa sin ella). Se guarda **cuándo y qué versión** aceptó la persona (`users.terms_accepted_at`, `users.terms_version`): la Ley 1581 exige poder probar la autorización.
- Crea: clínica, sede principal, la persona como **Administrador**, reglas de recordatorio por defecto y **15 días de prueba con el plan Pro, sin tarjeta**.
- Entra directo a la app con la sesión abierta y ve la bienvenida.
- Límite de registros por conexión (anti-abuso): el BFF reenvía la IP real.

**Términos** (`/terminos`) y **política de tratamiento de datos** (`/privacidad`): páginas públicas con un **texto base pendiente de revisión legal**. Si cambian los textos, se sube la versión en `config/app.php` → `terms_version` (y en la fecha de cada página): cada persona conserva la versión que aceptó.

**Landing (`/`)**: la página pública de Zooetia lleva a `/registro`; los precios los toma de `GET /public/plans` (`Plan::catalog()`, el mismo catálogo de Plan y facturación) y se refrescan cada 10 minutos. Cambiar un precio en la tabla `plans` lo cambia en ambos lados.

## 2. "Primeros pasos" (inicio, solo administradores)

Tarjeta con 6 pasos que **se marcan solos con datos reales**: datos de la clínica (NIT, dirección, teléfono), logo, horario, servicios, equipo (invitación o segundo miembro) y primer tutor. Barra de avance; cada paso lleva a su pantalla. Desaparece al completar todo o con "Ocultar" (`clinics.settings.onboarding_dismissed_at`).

## 3. Estados de la suscripción

| Estado | Qué significa | ¿Puede registrar cambios? |
|---|---|---|
| `trialing` | Prueba gratis (15 días) | Sí, hasta `trial_ends_at` |
| `active` | Plan pagado hasta `current_period_end` | Sí |
| `past_due` | Se venció el período sin pago | Sí, durante **5 días de gracia** (`Subscription::GRACE_DAYS`) |
| `expired` | Prueba o plan vencido | **No: modo consulta** (todo visible, nada editable; no se pierde nada) |
| `canceled` | Reemplazada por otra (p. ej. la prueba al activar un plan) | — |

Tareas nocturnas (hora Colombia): `subscriptions:expire-trials` (00:10) y `subscriptions:check-periods` (00:15: `active` → `past_due` → `expired`).

En la barra superior siempre hay un acceso a la pantalla del plan: "Prueba gratis: N días", **"Pago pendiente"** o **"Modo consulta · Activar plan"**.

## 4. Configuración → Plan y facturación (administradores)

- Estado actual en palabras ("Prueba gratis · 15 días restantes", "Plan Pro · activo, pagado hasta…", "pago pendiente: puedes seguir hasta…", "Tu plan está vencido").
- **Uso del mes**: mensajes de WhatsApp (con barra contra el límite del plan), sedes y personas del equipo.
- **Comparación de planes** con precio y lo que incluye cada uno; lo que aún no existe sale como "próximamente" (hoy solo la facturación DIAN).
- **Elegir un plan** → hoy deja una **solicitud** ("el equipo de Zooetia te contacta") guardada en `clinics.settings.plan_request` y en el log. Cuando llegue Mercado Pago, ese mismo botón abrirá el pago.
- Funciona **aunque la cuenta esté en modo consulta**: sus rutas están por fuera del bloqueo de escritura (si no, una clínica vencida no podría salir de ese estado).

## 5. Activación manual (mientras llega Mercado Pago)

Después de recibir el pago (transferencia, Nequi…), alguien del equipo de Zooetia ejecuta en el servidor:

```
php artisan subscriptions:activate <slug-de-la-clinica> <plan> --months=1 --reference="NEQUI-123"
```

- Planes: `basic`, `pro`, `pro_invoicing`. Meses: 1 a 24.
- **Mismo plan aún vigente** → los meses se suman **después del fin del período** (pagar antes no hace perder días).
- **Otro caso** (prueba, cambio de plan, plan vencido) → el plan empieza hoy y la suscripción anterior queda `canceled`.
- Borra la solicitud pendiente de la clínica.
- El slug de la clínica es el de su enlace (p. ej. `veterinaria-patitas`).

Toda la lógica está en `App\Billing\ActivatePlan`: **el webhook de Mercado Pago llamará a esa misma acción** con `provider = 'mercadopago'`.

## 6. Comparación de pasarelas para cobro mensual (2026-10-03)

> Objetivo: cobro mensual automático al menor costo posible.

Tarifas de referencia del plan estándar, sin negociar volumen ([Mentora Colombia](https://mentoracolombia.com/pasarelas-de-pago-colombia-2026-comisiones-wompi-bold-mercadopago/), [Bytechhub](https://bytechhub.com/blog/pasarelas-de-pago-en-colombia-comparativa-2026/)). **El IVA (19 %) se cobra sobre la comisión.** Confirmar con cada pasarela al firmar.

| Pasarela | Tarjeta | ¿Cobro automático mensual? | Costo por un pago de 99.000 |
|---|---|---|---|
| **Mercado Pago** | 3,29 % + 600 (dinero al instante) · 2,79 % + 600 (a 14 días) | **Sí, nativo** ("Suscripciones"): ellos cobran cada mes, reintentan y avisan | ~4.600 al instante · **~4.000** a 14 días |
| **Wompi** (Bancolombia) | ~2,65–2,99 % + 600–700 | Sí, guardando la tarjeta (o Nequi) como "fuente de pago": **nosotros** disparamos el cobro cada mes | **~4.000–4.200** |
| **ePayco** | ~2,79–3,5 % + 700 | Sí (planes y suscripciones) | ~4.100–5.000 |
| **PayU** | ~2,99 % + 700 | Sí (pagos recurrentes) | ~4.350 |
| PSE / transferencia | más barato (~1,5 % + 1.200 en Wompi) | **No**: la clínica paga a mano cada mes | ~3.200 |
| Stripe | — | No opera para empresas colombianas sin una empresa en el exterior | — |

**Conclusión:** todas cuestan **~4 % por cobro**; la diferencia entre ellas es de unos cientos de pesos por mes. Lo que decide es **cuánto trabajo nos ahorran**:
- **Mercado Pago Suscripciones**: menos código (ellos cobran, reintentan y piden actualizar la tarjeta vencida). Si se elige el desembolso a 14 días, cuesta lo mismo que Wompi.
- **Wompi**: misma tarifa, empresa colombiana, acepta Nequi; pero el cobro mensual, los reintentos y los avisos los programamos nosotros (ya tenemos los períodos y la gracia en `subscriptions:check-periods`, así que es viable).
- **Plan anual** (p. ej. 10 meses pagando 12): una sola comisión fija al año, mejor caja y menos clínicas que se van.

**Ojo con el IVA de la suscripción:** la computación en la nube puede estar **excluida de IVA** en Colombia si cumple los requisitos (art. 476 E.T.). Si no aplica, el precio de 99.000 incluiría 19 % de IVA y el ingreso real sería ~83.200: eso pesa mucho más que la pasarela. **Validar con el contador** antes de fijar precios.

## 7. Pendiente: Mercado Pago

Lo que falta decidir e implementar (fase de cobro en línea):

1. **Modelo de cobro**: suscripción automática (Mercado Pago "preapproval", cobra cada mes a la tarjeta) o pago mes a mes con enlace (Checkout Pro: tarjeta, PSE, Nequi, efectivo). Recomendación inicial: Checkout Pro mensual (más medios de pago en Colombia) y luego suscripción automática como opción.
2. Cuenta de Mercado Pago de Zooetia y credenciales (prueba y producción) en `.env`, nunca en el repositorio.
3. Webhook firmado → verificar el pago con la API de Mercado Pago → `ActivatePlan`. Debe ser **idempotente** (el mismo pago notificado dos veces no suma dos meses): guardar los pagos procesados en una tabla `subscription_payments`.
4. Correo de recibo y aviso antes del vencimiento (3 días antes y el día del vencimiento).
5. Precios definitivos ([`planes.md`](planes.md)) y si habrá precio anual.
6. Factura electrónica de Zooetia a la clínica (DIAN) por la suscripción.

## Código

| Pieza | Dónde |
|---|---|
| Registro | `Actions/Clinics/RegisterClinic`, `Requests/Auth/RegisterRequest`, web `app/(auth)/registro`, `app/api/auth/register/route.ts` |
| Términos y privacidad | web `app/(legal)/terminos`, `app/(legal)/privacidad`; versión en `config('app.terms_version')` |
| Primeros pasos | `OnboardingController`, web `app/app/OnboardingCard.tsx` |
| Estados y gracia | `Models/Subscription` (`allowsWrites`, `graceEndsAt`, `latestForClinic`) |
| Activar / renovar | `Billing/ActivatePlan`, comando `subscriptions:activate` |
| Vencimientos | comandos `subscriptions:expire-trials`, `subscriptions:check-periods` |
| Pantalla de plan | `BillingController` (`GET billing`, `POST billing/request`), web `configuracion/sections/BillingSection.tsx`, insignia en `layout/AppHeader.tsx` |
| Pruebas | `tests/Feature/Auth/RegisterTest.php`, `tests/Feature/Clinic/SubscriptionTest.php` |
