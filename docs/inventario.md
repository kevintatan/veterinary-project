# Inventario e ingresos (Fase 5a)

> Estado al 2026-10-05: **Fase 5a completa** (V1 inventario, V2 ventas y pagos, V3 conexión con la atención, V4 reportes y exportación). La facturación electrónica DIAN es la Fase 5b. La **facturación electrónica DIAN** es la Fase 5b, aparte y con asesoría contable.

## Decisiones de la Fase 5a

| Tema | Decisión |
|---|---|
| Vender o aplicar algo sin existencias | **Se permite, con aviso**: el inventario queda en negativo y se marca para corregir (en la práctica se aplica la vacuna antes de registrar la compra). |
| Caja con apertura y cierre (arqueo) | **No por ahora**: solo un "cierre del día" como resumen (V4). |
| Precios | **Con IVA incluido** (lo que paga el tutor); el IVA se calcula por dentro para reportes. Qué lleva IVA: validar con el contador. |
| Lotes y vencimientos | Se guardan en cada entrada y alimentan las alertas, **sin existencias separadas por lote**. |
| Permisos | Ver: todos. Gestionar (productos, entradas, traslados, importar): administrador y recepción. **Ajustes y costos: solo administrador.** |
| Plan | Inventario y cobro básico en **todos los planes**; reportes avanzados y exportación desde Pro. |
| Comprobante | El que se entregue al tutor dirá "Comprobante de pago · no es factura electrónica". |

## V1 — Productos e inventario

**Menú → Inventario** (`/app/inventario`).

**Productos:**
- Nombre, código (opcional, código de barras o referencia), tipo (producto, medicamento, vacuna, insumo), unidad (unidad, caja, ml, tableta, dosis, g, kg, frasco, pipeta), precio de venta con IVA incluido, IVA (0, 5 o 19 %), costo (solo administrador), existencia mínima, activo/inactivo.
- **"Controlar existencias"** se puede desactivar para lo que no se cuenta. No se puede desactivar si el producto tiene existencias (primero se ajustan a cero).
- **Sin duplicados:** no se repiten nombres (sin importar mayúsculas ni tildes: "VACUNA MÚLTIPLE" = "vacuna múltiple") ni códigos.
- Al crear un producto se puede poner su **existencia inicial** en la sede, con lote y vencimiento.

**Existencias por sede** (selector de sede arriba a la derecha):
- Estados: normal, **bajo** (en el mínimo o por debajo), **agotado** (0), **en negativo** (más salidas que entradas), **no se maneja aquí** (la sede nunca lo ha tenido: no cuenta como agotado ni genera alertas), sin control.
- Filtros: búsqueda por nombre o código, tipo, "bajos, agotados o en negativo", inactivos.

**Movimientos** (nunca se borran; cada uno guarda quién, cuándo, sede, cantidad y **cuánto quedó**):

| Movimiento | Quién | Qué pide |
|---|---|---|
| **Entrada (compra)** | Administrador y recepción | Cantidad, costo unitario (actualiza el costo del producto), lote, vencimiento, nota (proveedor, factura de compra) |
| **Ajuste** | Solo administrador | Motivo: **conteo físico** (se escribe lo que se contó y el sistema calcula la diferencia), daño, pérdida, vencido u otro (con nota obligatoria) |
| **Traslado** entre sedes | Administrador y recepción | Sede de origen y destino; queda una salida y una entrada enlazadas |
| Existencia inicial | Al crear o importar | — |

Los formularios muestran **cuánto quedará** antes de guardar ("Existencia actual: 9 → quedará en 8").

**Panel del producto:** precio, costo y **margen**, mínimo, existencias en cada sede y el historial completo de movimientos.

**Alertas** (pestaña "Alertas", con contador):
- **Existencias bajas**: productos en el mínimo o por debajo, agotados o en negativo, con botón "Registrar entrada".
- **Lotes por vencer**: entradas con vencimiento en los próximos **60 días** (o ya vencidas) mientras la sede tenga existencias. Acciones: **"Dar de baja"** (ajuste "Vencido" ligado a ese lote; solo administrador) o **"Ya lo revisé"** (oculta el aviso).
- **Aviso diario en la campana** (7:00 a. m., hora Colombia) para quien gestiona inventario: "Inventario: 2 productos con existencias bajas y 1 lote por vencer", por sede. Las clínicas en modo consulta no reciben el aviso.

**Importar desde Excel** (`Importar desde Excel`):
1. Descargar la plantilla (columnas: nombre, código, tipo, unidad, precio de venta, costo, IVA, existencia mínima, existencia inicial; solo el nombre es obligatorio).
2. Llenarla en Excel y guardarla como **CSV** (Archivo → Guardar como → CSV).
3. Subirla. Acepta lo que produce Excel en español: separador `;` o `,`, tildes de Windows, precios como `45.000` o `$ 45.000`, decimales con coma, y nombres de columna parecidos ("Precio", "Mínimo", "Existencia"…).
- Si el producto ya existe (mismo código o nombre) **se actualizan sus datos y precios, pero no sus existencias** (esas solo cambian con movimientos).
- Cada fila es independiente: las que tienen error se listan con el número de fila y el motivo, y las demás se importan. Volver a subir el archivo corregido no duplica nada.
- Hasta 2.000 productos por archivo. (El formato .xlsx directo requeriría una librería adicional; por ahora CSV.)

**Limitación conocida (por la decisión de no llevar existencias por lote):** al trasladar a otra sede, el lote y su vencimiento no viajan con las unidades; la alerta de vencimiento queda en la sede donde entró el lote.

## V2 — Ventas y pagos

**Menú → Ventas** (`/app/ventas`) y la tarjeta **Cuenta** en la ficha de cada tutor.

**La venta (cuenta):**
- Para un tutor (queda en su cuenta) o **de mostrador** (sin tutor). Se crea desde Ventas → "Nueva venta" o desde la ficha del tutor.
- **Consecutivo por clínica** (N.º 000001, 000002…), que nunca se repite ni se borra.
- Guarda una **foto de los datos del cliente** al momento de vender (nombre, documento, celular): si después cambian en la ficha, el comprobante sigue igual.
- El cliente se puede cambiar mientras la venta esté abierta.

**Qué se vende:**
- Buscador de **productos y servicios** (muestra precio y cuántos hay en la sede) y **cargo libre** (descripción, precio, cantidad e IVA: por ejemplo "Corte de uñas").
- Los **productos descuentan existencias** de la sede de la venta al agregarlos; si se cambia la cantidad o se quita el ítem, vuelven al inventario.
- Cantidad, precio y descuento editables por ítem, y **descuento a toda la venta** (el mismo % a cada ítem).
- Precios **con IVA incluido**: cada ítem calcula cuánto de su valor es IVA (para reportes).

**Pagos:**
- Efectivo, tarjeta, transferencia, Nequi, Daviplata u otro, con referencia (número de aprobación o de transferencia).
- **Pagos parciales**: la venta pasa de *Pendiente* a *Abonada* y a *Pagada*. No se puede pagar más que el saldo.
- En efectivo, ayuda con el **cambio** ("Recibido: 110.000 → Cambio: 10.000"); se registra solo el valor de la venta.
- **Pagada = cerrada**: ya no se cambian sus ítems; si hubo un error, se anula y se hace otra.

**Anular** (solo administrador, con motivo; nada se borra):
- **Un pago**: queda tachado con su motivo y el saldo se reabre.
- **La venta**: los productos vuelven al inventario; si tenía pagos, la pantalla recuerda devolverlos al tutor.

**Comprobante** (PDF media carta, botón "Comprobante"): membrete de la clínica, número, fecha, cliente, quién atendió, ítems, descuentos, IVA incluido, total, pagos, saldo y la leyenda **"Comprobante de pago · no es factura electrónica"**. Si la venta está anulada, lo dice.

**Ficha del tutor → Cuenta**: "Al día" o "Debe $40.000 en 2 ventas", últimas ventas y "Nueva venta".

**Lista de ventas**: pendientes, pagadas, anuladas o todas; búsqueda por número o nombre del cliente; rango de fechas; total por cobrar de la lista.

**Permisos**: ver ventas: todos; crear ventas, agregar ítems y cobrar: administrador y recepción (`sales.manage`); anular ventas y pagos: solo administrador (`sales.void`). En V3 el veterinario podrá agregar cargos desde la atención.

## V3 — Conexión con la atención

**La cuenta del día:** todo lo que se cobra de una visita va a **una sola cuenta**: la que el tutor tenga abierta (pendiente o abonada) **hoy, en esa sede**. Si no tiene, se crea. Si la de hoy ya está pagada, lo nuevo abre otra.

**"Cobrar" desde la cita** (panel de la cita en la agenda, para quien cobra):
- Abre la cuenta del día del tutor con el **servicio de la cita** ya cargado (marcado "Cita del 5 de oct., 9:00 a. m.").
- Si la cita **ya se cobró**, lleva a esa misma venta: nunca se cobra dos veces.
- No aparece en citas canceladas, sin asistencia o en solicitudes en línea sin aceptar; una cita sin tutor se cobra como venta de mostrador.

**Producto del inventario en la historia clínica** (vacuna, desparasitación o procedimiento, al crear el registro):
- El veterinario busca el producto del inventario (muestra existencias y precio). Al elegirlo se llenan solos el **nombre** y, en vacunas, el **lote y su vencimiento** (de la última entrada registrada).
- **"Cargar a la cuenta del tutor"** (marcado por defecto): el producto se suma a la cuenta del día y sale del inventario como venta. El veterinario no necesita permiso de cobro para esto.
- Desmarcado (incluido o de cortesía): **solo se descuenta del inventario** (movimiento "Uso en atención").
- Todo ocurre **junto con el registro**: si algo falla, no queda ni el registro ni el cobro a medias.
- En la venta, el ítem dice **"Desde la historia: Vacunación · Rocky"**, con enlace a la mascota.

**Ten en cuenta:** si después se **anula el registro clínico**, el cargo no se quita solo de la venta: hay que quitarlo (si la venta sigue abierta) o anular la venta. Solo al crear el registro se puede indicar el producto (al editarlo, no), para no mover existencias dos veces.

## V4 — Reportes y exportación

**Menú → Reportes** (`/app/reportes`). Dos números que nunca se mezclan:
- **Ventas**: lo vendido en el periodo (ventas no anuladas, por su fecha).
- **Recaudado**: el dinero que entró en el periodo (pagos no anulados, por su fecha), que puede pagar ventas de otros días.

**Cierre del día** (quien cobra: recepción y administrador; todos los planes):
- Recaudado (indicando cuánto fue de **ventas de días anteriores**), ventas del día y lo que **quedó por cobrar**.
- **Por medio de pago** (para cuadrar la caja y los extractos) y **quién recibió** cada pago.
- Lista de ventas del día con saldo (enlace a cada una) y **anulaciones** del día con su motivo (y si una venta anulada tenía pagos que hay que devolver).
- Por sede o todas; cualquier fecha; **"Imprimir cierre"** (al imprimir se ocultan el menú y la barra superior).

**Ventas del periodo** (solo administrador, permiso `reports.view`; **desde el plan Pro**, función `advanced_reports`):
- Atajos: hoy, 7 días, este mes, mes anterior, o fechas a mano (máximo un año); por sede.
- Ventas, recaudado, número de ventas, promedio por venta, por cobrar e IVA incluido, **comparados con el periodo anterior** de la misma duración.
- Gráfico de **ventas por día** (con "Ver tabla", que también muestra lo recaudado cada día).
- **Servicios y productos más vendidos**, por tipo (servicios, productos, cargos libres), por **medio de pago** y por **vendedor**.
- En el plan Básico se muestra "disponible desde el plan Pro"; el cierre del día sigue disponible.

**Exportar a Excel** (mismo permiso y plan): dos archivos CSV listos para Excel en español (separador `;`, decimales con coma, tildes correctas):
- **Ventas**: una fila por ítem (fecha, número, estado, cliente, documento, sede, vendedor, tipo, descripción, cantidad, valor unitario, descuento, IVA %, IVA incluido, total).
- **Pagos**: una fila por pago (fecha, venta, cliente, documento, medio, referencia, valor, quién recibió, válido o anulado y motivo).

**Inicio → "Recaudado hoy"** (quien cobra): recaudado, ventas y por cobrar de hoy, por medio de pago, con enlace al cierre del día.

## Código

| Pieza | Dónde |
|---|---|
| Tablas | `products`, `inventory_stocks`, `inventory_movements` (migración `2026_10_11_000001`) |
| Modelos | `Models/Inventory/{Product,InventoryStock,InventoryMovement}` |
| Toda variación de existencias | `Inventory/Inventory` (`purchase`, `initial`, `adjust`, `transfer`, `closeLot`, `record`): transacción + fila de existencia bloqueada |
| Alertas | `Inventory/InventoryAlerts`, comando `inventory:alerts` (diario 07:00) |
| Importación | `Inventory/ProductImport` |
| API | `ProductController` (`products`), `InventoryController` (`products/{id}/purchase|adjust|transfer`, `inventory/alerts`, `inventory/import`, `inventory/import-template`, `inventory/lots/{id}/close`) |
| Permisos | `inventory.view`, `inventory.manage`, `inventory.adjust` |
| Web | `app/app/inventario/` (`InventoryView`, `ProductPanel`, `ProductFormPanel`, `MovementModal`, `ImportModal`), `lib/inventory.ts` |
| Ventas: tablas | `sales`, `sale_items`, `payments` (migración `2026_10_12_000001`) |
| Ventas: toda operación | `Sales/Sales` (`open`, `setCustomer`, `addItem`, `updateItem`, `removeItem`, `discountAll`, `addPayment`, `voidPayment`, `void`): venta bloqueada, totales y estado recalculados, existencias en la misma transacción |
| Comprobante | `Sales/ReceiptPdf`, vista `resources/views/pdf/receipt.blade.php` |
| API de ventas | `SaleController` (`sales`, `sales/{id}/items|discount|payments|void|receipt`, `sale-items/{id}`, `payments/{id}/void`, `sellables`, `owners/{id}/account`) |
| Web de ventas | `app/app/ventas/` (`SalesView`, `NewSaleModal`, `[id]/SaleView`), `app/app/tutores/[id]/AccountCard.tsx`, `lib/sales.ts` |
| Conexión con la atención | `Sales/CareCharges` (`chargeAppointment`, `useProduct`), `Sales::accountFor` (cuenta del día), `sale_items.appointment_id` y `clinical_record_id` (migración `2026_10_13_000001`), `product_use` en `ClinicalRecordRequest` y `SaveClinicalRecord`, `POST appointments/{id}/charge` |
| Web de la conexión | Botón "Cobrar" en `agenda/AppointmentDetailPanel.tsx`, `mascotas/[id]/ProductUseField.tsx` en `RecordFormPanel.tsx` |
| Reportes | `Sales/SalesReports` (`dailyClose`, `period`, `csv`), `ReportController` (`reports/daily-close`, `reports/sales`, `reports/sales/export`), permiso `reports.view`, función de plan `advanced_reports` |
| Web de reportes | `app/app/reportes/ReportsView.tsx`, `app/app/IncomeCard.tsx`, `components/charts/ChartCard.tsx` (compartido con el inicio) |
| Pruebas | `tests/Feature/Inventory/InventoryTest.php`, `tests/Feature/Sales/SaleTest.php`, `tests/Feature/Sales/CareChargesTest.php`, `tests/Feature/Sales/ReportTest.php` |
