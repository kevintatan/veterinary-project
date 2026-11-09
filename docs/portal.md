# Portal del tutor (Fase 4e)

El dueño de la mascota entra desde su celular a **`/portal`** y ve, de **todas** las clínicas Zooetia que lo invitaron: sus mascotas, el **carné con QR** (el mismo diseño que eligió la clínica; lo voltea y lo descarga), las **vacunas** y las **próximas citas**.

## Cómo entra el tutor (una sola vez)

```
Clínica: ficha del tutor → "Portal del tutor" → Invitar por WhatsApp / Por correo / Copiar enlace
   │   (WhatsApp = abre el WhatsApp de la clínica con el texto listo: no usa Meta, es gratis)
   ▼
Tutor abre /portal/activar/{token}   (enlace de un solo uso, vence en 7 días)
   ├─ primera vez:   nombre + correo + contraseña → entra y queda con sesión 180 días
   └─ ya tiene cuenta (otra clínica lo invitó antes, mismo celular):
                     ingresa con su correo → "Agregar {clínica} a mi cuenta" (un toque)
Después: /portal/ingresar con correo y contraseña. Sin códigos.
¿Olvidó la contraseña? /portal/recuperar → código de 6 dígitos por CORREO (gratis, 15 min, 5 intentos)
                        → contraseña nueva → entra, y se cierran sus demás sesiones.
```

**Por qué así** (decidido 2026-10-02, ver `fase-4.md` → "Acceso del tutor"):
- **Costo:** un código por WhatsApp en cada ingreso es un mensaje *authentication* que cobra Meta, y se multiplica por ingresos y tutores. Aquí Meta no interviene.
- **Identidad:** el enlace solo llega a quien la clínica tiene registrado (celular o correo, con su autorización de datos). **Qué mascotas ve lo decide el registro de la clínica**, no datos que escriba el tutor (nombre o microchip de la mascota se adivinan: no sirven para validar).
- **Una cuenta por persona:** la identidad es el **celular** del tutor (se toma de la invitación, no lo escribe). Si otra clínica invita el mismo celular, se suma a la misma cuenta. Una invitación de otro celular se rechaza aunque se reenvíe.

## Seguridad

| Riesgo | Cómo se cubre |
|---|---|
| Un token del portal llamando rutas del personal (o al revés) | Tokens distintos: el del tutor tiene la habilidad `tutor` y **ninguna clínica**. `ResolveClinicFromToken` exige clínica; `EnsureTutorToken` exige `tutor`. El BFF del portal (`/api/tutor/*`) solo alcanza rutas `portal/*` del API. |
| Ver mascotas ajenas | `TutorPortal::withPet` comprueba que la mascota sea de un tutor vinculado a la cuenta; si no, **404** (ni siquiera confirma que exista). Toda lectura ocurre **dentro** de la clínica de la mascota (`runAs`): el aislamiento por clínica sigue aplicando. |
| Robo de la base de datos | De la invitación solo se guarda el **sha256** del enlace. |
| Reusar o adivinar enlaces | Un solo uso (con bloqueo de fila), vencen en 7 días, 48 caracteres aleatorios, *rate limit* por IP (el BFF reenvía la IP del visitante). |
| Fuerza bruta al ingresar | *Rate limit* `login` (5/min por correo+IP). |
| Recuperar contraseña | La respuesta es la misma exista o no el correo (no revela cuentas). Código de 6 dígitos guardado como HMAC, 15 min, se bloquea a los 5 intentos fallidos, solo vale el último. Límites: 5 envíos/hora por correo, 15 intentos/min por IP. Al cambiarla se cierran todas las sesiones. |
| El token en el navegador | Vive en la cookie httpOnly `zoo_tutor` (separada de `zoo_session` del personal). |

## Dónde está el código

| Pieza | Archivo |
|---|---|
| Tablas | `api/database/migrations/2026_10_07_000001_create_tutor_portal_tables.php` |
| Invitar / aceptar | `app/Actions/Portal/InviteOwnerToPortal.php`, `AcceptPortalInvitation.php` |
| Recuperar contraseña | `app/Actions/Portal/ResetPortalPassword.php` (tabla `tutor_login_codes`), página `web/src/app/portal/recuperar` |
| Lecturas entre clínicas | `app/Portal/TutorPortal.php` |
| API del portal | `app/Http/Controllers/Api/V1/Portal/*` (rutas `portal/*` en `routes/api.php`) |
| API para la clínica | `PortalInvitationController` (`GET owners/{id}/portal`, `POST owners/{id}/portal-invitation`) |
| Datos del carné (compartidos con el diseñador) | `app/Pets/PetCard.php` |
| Web: sesión y BFF | `web/src/lib/server/tutor-session.ts`, `app/api/tutor-auth`, `app/api/tutor/[...path]`, `proxy.ts` |
| Web: páginas | `web/src/app/portal/*` (inicio, `ingresar`, `activar/[token]`, `mascotas/[id]`) |
| Web: tarjeta en la ficha | `web/src/app/app/tutores/[id]/PortalCard.tsx` |
| Pruebas | `api/tests/Feature/Portal/TutorPortalTest.php` |

**Probar en local:** `paola@anipet.test` / `password1` en `/portal/ingresar` (`DemoPortalSeeder`), o invitar a cualquier tutor con celular desde su ficha → *Copiar enlace* → abrirlo en una ventana privada.

## Documentos en el portal

Si la clínica le envía un consentimiento para firmar, en el inicio aparece **"Tienes un documento por firmar"**; lo lee y firma sin escribir los dígitos de su documento (la sesión ya lo identifica). Debajo, **"Documentos firmados"** con el PDF de cada uno. Ver [`documentos.md`](documentos.md).

## Pedir una cita

Desde el inicio del portal y desde el carné de cada mascota, el tutor pide una cita con sus datos y mascotas ya llenos; llega a la clínica como solicitud, igual que la reserva pública. Ver [`reservas.md`](reservas.md).

## Pendiente (siguientes pasos del portal)

- **Recuperar sin correo:** si el tutor ya no tiene acceso a su correo, la clínica lo invita de nuevo… pero hoy eso choca con "ya tienes cuenta". Opción: código por WhatsApp como respaldo, o que la clínica pueda reiniciar el acceso.
- **Fórmulas y documentos compartidos:** columna `visible_to_owner` en registros y adjuntos (`database.md` §10b).
- **Modo "mascota perdida" desde el portal** (hoy lo activa la clínica).
- **App móvil:** mismo API `portal/*`; el token ya es de larga duración, se agrega biometría en el dispositivo.
