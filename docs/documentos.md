# Documentos y firmas (consentimientos firmados en pantalla)

El veterinario prepara un consentimiento o una autorización desde la ficha de la mascota; el formato **se llena solo** con los datos de la clínica, el tutor, la mascota y el veterinario; se lee con el tutor y **todos firman en la pantalla** (con el dedo en una tablet o con el mouse). Al completar las firmas queda un **PDF firmado** que no cambia nunca, con una hoja de evidencias. Idea tomada de VetPraxis (2026-10-03).

**Plan:** disponible en **todos los planes** (no usa mensajes de Meta y es de uso diario).

## Estado

| Paso | Qué | Estado |
|---|---|---|
| **D1** | Plantillas base, formulario por tipo con datos que se llenan solos, vista previa y PDF | ✅ |
| **D2** | Firma en pantalla en la clínica (tutor, veterinario y testigo opcional), PDF congelado con evidencias, anulación | ✅ |
| **D3** | Firma a distancia: enlace de un solo uso por WhatsApp de la clínica, correo o portal del tutor | ✅ |
| **D4** | La clínica ajusta el texto de sus plantillas | ✅ |

## Tipos de documento (primera versión)

| Tipo | Qué pide según el caso | Firman |
|---|---|---|
| **Eutanasia** | Diagnóstico, motivo, destino del cuerpo (cremación individual o colectiva, el tutor lo retira, disposición sanitaria), si el tutor quiere estar presente, valor | Tutor, veterinario y, si se marca, **testigo** |
| **Cirugía y anestesia** | Procedimiento, tipo de anestesia, riesgos particulares, indicaciones previas (ayuno), costo estimado, monto adicional autorizado ante complicaciones | Tutor y veterinario |
| **Hospitalización** | Motivo, días estimados, valor por día, anticipo, contacto para novedades | Tutor y veterinario |
| **Alta voluntaria** (retiro contra recomendación médica) | Lo que recomendó el veterinario, riesgos explicados, motivo del tutor | Tutor, veterinario y testigo opcional |

> **Revisión legal pendiente.** Los textos son una base razonable, **no revisada por un abogado**. Deben revisarse antes de usarlos con clínicas reales. Los de Zooetia están en `api/app/Documents/DocumentTypes.php` (al cambiar un texto se sube `version`; los documentos ya firmados conservan el texto con el que se firmaron). Cada clínica puede además ajustar el suyo (D4).

## Cómo se usa

1. Ficha de la mascota → tarjeta **Documentos** → **+ Nuevo**.
2. Se elige el tipo, el **veterinario responsable** (debe tener tarjeta profesional registrada) y se llenan solo los datos del caso. En eutanasia y alta voluntaria se puede pedir **testigo**.
3. **Crear y revisar** abre el documento como se va a imprimir (`/app/documentos/{id}`). Mientras nadie firme se puede **Editar** y ver el PDF con la marca "BORRADOR".
4. Cada firmante toca su recuadro: **Firmar (tutor)**, **Firmar como veterinario**, **Firmar (testigo)**. En la ventana firma en el recuadro y marca *"Leí el documento completo, entendí su contenido y lo acepto"* (el testigo: *"Presencié la lectura y la firma"*). El botón Firmar solo se activa con firma y casilla.
5. Con la **primera firma el texto queda bloqueado**. Con la última, el documento queda **Firmado** y se genera el **PDF firmado** (botón "PDF firmado").
6. ¿Algo quedó mal? **Anular** con motivo (no se borra) y crear uno nuevo.

## Firma a distancia (D3)

Para cuando quien autoriza no está en la clínica (por ejemplo, el dueño de viaje mientras un familiar trae a la mascota).

1. En el documento, debajo de "Firmar (tutor)": **o enviar para firmar a distancia** → **Por WhatsApp** (abre el WhatsApp de la clínica con el mensaje y el enlace listos, gratis), **Por correo** o **Copiar enlace**. Las opciones sin dato (sin correo o sin celular) aparecen desactivadas con el motivo.
2. **Al enviarlo, el texto queda bloqueado** (estado "Firmando"): el tutor firma exactamente lo que se le envió.
3. El tutor abre `/firmar/{enlace}` en su celular: lee el documento, escribe los **últimos 4 dígitos de su documento** y firma con el dedo. Ve una confirmación clara al terminar.
4. Si el tutor tiene el **portal**, ahí le aparece **"Tienes un documento por firmar"**: firma sin escribir los dígitos (la sesión ya lo identifica). En el portal también ve sus **documentos firmados** con el PDF para descargar.
5. En la clínica, el recuadro del tutor muestra "Enviado por WhatsApp · vence…" con **Cancelar enlace**, y después "Firmó… · A distancia, por enlace" (o "En el portal del tutor"). El veterinario firma después desde su sesión, como siempre.

**Reglas del enlace:** un solo uso; vence en **48 horas**; se **bloquea con 5 intentos** fallidos de los dígitos; uno nuevo reemplaza al anterior; anular el documento lo invalida. Del enlace solo se guarda su hash.

**Por qué los 4 dígitos (y por qué se ocultan):** el enlace prueba que llegó al teléfono o correo del tutor; los dígitos prueban que quien firma es el tutor y no alguien que vio el mensaje. Por eso, **en la página del enlace el número de documento aparece enmascarado** (`••••••••`) aunque el texto lo mencione: si se mostrara, el dato que se pide quedaría a la vista. El PDF firmado sí lo lleva completo.

## Textos propios de la clínica (D4)

**Configuración → Documentos** (administradores). **Se edita sobre el documento mismo**, como en un procesador de texto: una hoja tamaño carta con el membrete real de la clínica (logo, nombre, NIT), la tabla de datos, las líneas de firma y el pie, igual que el PDF firmado.

- **Clic en un párrafo** lo convierte en una caja de edición con la misma letra, que crece con el texto. Esc o clic afuera para terminar.
- Mientras se edita, los datos aparecen como **«Nombre del tutor»**, «Documento del tutor»… (no como códigos); en la hoja se ven con **valores de ejemplo resaltados** (verde: automáticos; ámbar: datos del caso). El interruptor "Con datos de ejemplo / Con etiquetas" cambia la vista.
- **Panel lateral** con los datos para insertar donde está el cursor, agrupados en "De este caso" y "Automáticos".
- **Herramientas de cada párrafo** (subir, bajar, cuándo se muestra, eliminar): al pasar el mouse, o abajo de la caja mientras se edita. **"+ Párrafo / + Viñetas"** entre párrafos.
- Cada párrafo puede mostrarse **siempre** o **solo si se llena** un campo opcional (se marca en la hoja: "Solo si se llena «…»").
- Barra superior fija con **Descartar cambios / Guardar texto**; **Restaurar texto de Zooetia** vuelve a la versión base. Si se intenta salir con cambios sin guardar, el navegador avisa.
- Por dentro se guarda con los códigos de siempre (`{{tutor}}`): la conversión «Etiqueta» ↔ código la hace la pantalla. Una «Etiqueta» que no existe sale en rojo y no deja guardar.

**Reglas:**
- Solo cambia el **texto**: los campos del formulario y quién firma siguen siendo los de Zooetia.
- Se rechazan datos que no existen (p. ej. `{{mascot}}`), párrafos vacíos y textos que **no incluyan el nombre del tutor, su documento y el nombre de la mascota** (identifican a quien autoriza).
- Aplica a los **documentos nuevos**: los creados antes conservan su texto. Cada documento guarda con qué versión se hizo (`snapshot.template`: versión de Zooetia y versión de la clínica).
- Si Zooetia actualiza su texto base después de que la clínica editó el suyo, el editor lo avisa.
- Cada cambio queda en la auditoría (quién y cuándo).

## Reglas

- **Datos obligatorios:** el tutor debe tener **documento de identidad** registrado y el veterinario **tarjeta profesional** (Configuración → Mi perfil). Sin ellos no se puede crear el documento: identifican a quien autoriza y a quien responde.
- **El texto se congela al crear** el documento (*snapshot*): si después cambia el peso de la mascota o el nombre de la clínica, el documento no cambia.
- **La firma del veterinario la hace el veterinario responsable desde su propia sesión.** La del tutor y la del testigo se recogen en la sesión de quien atiende (queda registrado quién).
- **Una firma por rol**; no se puede volver a firmar ni firmar un documento anulado.
- **Recuadro vacío o garabato mínimo:** se rechaza ("La firma está vacía o es muy corta").
- **Quién puede:** crear y recoger firmas, quien escribe historia clínica (veterinarios y administradores); verlos, cualquiera que vea la historia (también recepción). **Anular:** quien lo creó, el veterinario responsable o un administrador.
- **Nunca se borra:** anulado con motivo, fecha y usuario; el PDF se conserva.

## Validez y evidencias (Ley 527 de 1999)

La firma electrónica es válida si es **confiable y apropiada** para el uso; para un consentimiento veterinario no se necesita firma digital con certificado. Zooetia guarda, por cada firma:

| Evidencia | Dónde |
|---|---|
| Quién firmó: nombre y documento (o tarjeta profesional) | `pet_document_signatures.signer_name`, `signer_document` |
| Qué firmó: **huella SHA-256 del contenido**, igual para todos los firmantes | `pet_documents.content_hash` y `pet_document_signatures.content_hash` |
| Cuándo y desde dónde: fecha y hora, IP, dispositivo (navegador y sistema) | `signed_at`, `ip`, `user_agent` |
| Cómo firmó: en la clínica, a distancia por enlace (con verificación de dígitos) o en el portal (sesión verificada) | `pet_document_signatures.method` (`in_person`, `remote_link`, `portal`) |
| Intención de firmar: casilla "leí y acepto" obligatoria + trazo | validación de la API + `image_path` |
| En qué sesión se recogió | `collected_by` |
| El archivo final: **huella SHA-256 del PDF** | `pet_documents.pdf_sha256` |

El PDF lleva al final una **hoja de evidencias** con esa tabla y la huella del contenido. El PDF firmado se genera **una sola vez** y siempre se entrega el mismo archivo (byte por byte, probado).

## Técnico

| Pieza | Archivo |
|---|---|
| Plantillas (textos, campos, firmantes) | `api/app/Documents/DocumentTypes.php` |
| Llenado y huella del contenido | `api/app/Documents/DocumentRenderer.php` |
| Firmar (validar y re-codificar el trazo, bloquear, finalizar) | `api/app/Actions/Documents/SignPetDocument.php` |
| PDF y hoja de evidencias | `api/app/Documents/DocumentPdf.php`, `resources/views/pdf/document.blade.php` |
| API | `PetDocumentController` (`GET document-types`, `GET/POST pets/{pet}/documents`, `GET/PATCH documents/{id}`, `POST documents/{id}/signatures`, `GET documents/{id}/pdf`, `POST documents/{id}/void`) |
| Firma a distancia | `api/app/Actions/Documents/RemoteSigning.php`, `PublicSigningController` (`GET/POST public/sign/{token}`), `Portal/PortalDocumentController` (`portal/documents…`); web `app/firmar/[token]`, `app/portal/documentos/[id]`, `components/domain/{SignHere,DocumentPaper}.tsx` |
| Textos de la clínica | `DocumentTemplateController` (`GET document-templates`, `PUT/DELETE document-templates/{tipo}`), datos disponibles en `DocumentTypes::PLACEHOLDERS`; web `configuracion/sections/DocumentsSection.tsx` |
| Tablas | `pet_documents`, `pet_document_signatures` (migración `2026_10_08_000001`), `pet_document_requests` (`…000002`), `document_templates` (`…000003`) |
| Web | `components/domain/SignaturePad.tsx` (lienzo propio, sin librería), `app/app/mascotas/[id]/DocumentsCard.tsx` y `DocumentFormPanel.tsx`, `app/app/documentos/[id]/` |
| Pruebas | `api/tests/Feature/Documents/PetDocumentTest.php` |

**Detalles que importan:**
- PostgreSQL guarda los objetos `jsonb` **reordenando las llaves**: el orden de los firmantes se fija en código (tutor, veterinario, testigo), nunca se confía en el orden del JSON. La huella del contenido se calcula siempre desde el *snapshot* guardado.
- El lienzo de firma no se borra cuando en el celular aparece o se esconde la barra del navegador (solo si cambia el ancho real).
- El proxy de Next (`/api/backend`) reenvía el navegador y la IP del visitante: sin eso la evidencia decía "navegador desconocido".
