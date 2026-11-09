# Benchmark: OkVet

> Relevado el 2026-09-29 sobre `sys.okvet.co` con una cuenta de prueba (clínica "Anipet", plan Gratis).
> Referencia principal de producto (ver `info.md` §1). Cada sección termina con **→ Zooetia**: qué proponemos hacer.
>Si
> Datos de prueba creados en la cuenta de OkVet (ficticios): propietaria *Carolina Restrepo Gómez* (CC 1098745312, sin email, canales SMS/WhatsApp desactivados), mascota *Luna* (ID historia 3094435) y una cita el 30/09/2026 10:00. Se activó el módulo de Agenda. Ventas **no** se activó (exige NIT).

## 1. Resumen ejecutivo

- OkVet es un producto **amplio y maduro**: agenda, historia clínica muy detallada, hospitalización con kardex, ventas/inventario, facturación DIAN (propia o vía Siigo), marketing por email, sala de espera, planes de salud, ~30 informes, IA integrada y una app para tutores (**OkPet**).
- **Diferenciadores de Zooetia que se confirman:**
  1. **WhatsApp:** OkVet no tiene bot; vende paquetes de mensajes (50/mes en Pro, +50 por $4.250). Solo recordatorios salientes.
  2. **Multi-sede:** en OkVet cada sede es **otra cuenta** "vinculada" (desvincular requiere soporte). No hay multi-sede real dentro de una clínica.
- **No es diferenciador frente a OkVet:** facturación electrónica DIAN (la tienen, incluida en Pro).
- **Brecha principal de nuestro brief:** la profundidad clínica. El modelo de datos del `info.md` §8 era demasiado simple; ya se reescribió con base en este benchmark.

## 2. Planes y precios (COP/mes, visto en la app)

| Plan | Precio | Límites / incluye |
|---|---|---|
| Gratis | $0 | HC, usuarios, propietarios, mascotas, agenda (50 citas/mes), 50 emails, 50 recibos, 5 GB, 50k tokens IA, archivos de 2 MB, HC "limitadas" |
| ONE | $69.900 | **1 usuario**, **máx. 20 registros/mes**; 25 SMS, 25 WhatsApp, 100 emails, 75k tokens IA. Sin FE, ventas, hospitalización ni marketing |
| Pro | $132.999 | Todos los usuarios; FE DIAN (300 docs/mes, factura, nota crédito, doc. soporte), kardex/hospitalización, informes premium, marketing, almacenamiento ilimitado (80 MB/archivo), 3000+ citas, 300+ recibos; 50 SMS, 50 WhatsApp, 3000 emails, 150k tokens IA |

Add-ons: 50 SMS $1.500 · 50 WhatsApp $4.250 · 500 emails $4.830 · 50 documentos electrónicos $12.880 · 150k tokens IA $6.300. Hay descuento anual y códigos de descuento. Un modal "Recursos del plan" muestra el consumo del mes por recurso.

**→ Zooetia:** nuestra propuesta ($49–69k Básico, $89–119k Pro, $129–169k Pro+FE) compite bien en el tramo alto. El riesgo es el **plan gratis** de OkVet: decidir entre plan free limitado o prueba gratis de 14–30 días. Medir el consumo por clínica (mensajes, documentos) desde el día 1, como hacen ellos.

## 3. Navegación (menú principal)

Dashboard · Administración (configuración, usuarios, propietarios, planes de salud, suscripción, variables/catálogos) · Agenda (general, personal, disponibilidad) · Consultorio (buscador, sala de espera, tareas) · Ventas · Hosp./Amb. · Solicitudes (HC, agendamientos, órdenes) · Marketing · Informes.

## 4. Configuración de la clínica (8 pestañas)

1. **General:** logo, razón social, NIT/CC (sin DV), teléfono, email, email de recordatorios, emails para el reporte ejecutivo mensual.
2. **Localización y servicios:** geolocalización y radio de cobertura, opción "sin dirección" (solo domicilio), dirección y ciudad, servicios ofrecidos (52), especies atendidas (14), precio promedio de consulta, 24 horas, idioma, moneda y zona horaria. Alimenta el directorio público de OkPet.
3. **Agenda y disponibilidad:** horario por día (varias franjas), duración de cita, **prevenir solapamientos**, usuarios en la agenda y administradores de agenda, recibir solicitudes de cita desde OkPet, evento de calendario por email al tutor, **recordatorios múltiples** (N minutos/horas/días antes × canal OkPet/Email/SMS/WhatsApp).
4. **Perfil fiscal:** tipo de persona, tipo de documento (lista DIAN), régimen IVA, responsabilidades fiscales, municipio DIAN.
5. **Ventas e inventario:** proveedor de FE (OkVet propio o **Siigo Nube por API**), **emisión automática al cerrar la cuenta** (con retraso opcional; si falla queda para facturar manual), POS, **turnos de caja**, no cerrar cuentas con saldo, impresión en tirilla, sobreventa (stock negativo), confirmación de entrega (picking). **Formularios vinculados:** cada registro clínico (consulta, vacuna, fórmula, cirugía, lab, spa, hospitalización por días) agrega su producto al **estado de cuenta** del propietario.
6. **Preferencias:** formato de impresión, **recordatorios por "próximo control"** (plantillas: simple / debe agendar / sin cita; reglas por tipo de registro × días/semanas × canal), notificar la carga de resultados, motivos de consulta visibles, campos condicionales (lista de problemas, diagnósticos diferenciales), mediciones del kardex, aviso de notas importantes al abrir el consultorio, **ventana de edición** (tras N horas solo el admin puede editar un registro clínico).
7. **Sala de espera:** turnos, consultorios, pantalla pública con banners.
8. **Sedes:** vincular *otras cuentas* OkVet para consultar historias de forma centralizada.

**→ Zooetia:** copiar la agenda configurable (franjas, duración, solapamientos, recordatorios por canal), los recordatorios por próximo control y la ventana de edición. **Multi-sede nativa** (`branches` dentro de la clínica) como diferenciador. Fuera del MVP: sala de espera, POS, turnos de caja y picking.

## 5. Propietarios (tutores)

Formulario: tipo y número de documento; móvil/WhatsApp con selector de país (validado en vivo); nombre o razón social; email (o "No tiene"); teléfono fijo; dirección, ciudad, región y localidad; **contacto autorizado** (nombre y teléfono); lugar de expedición del documento; **¿cómo nos encontró?** (16 opciones: Google, Instagram, TikTok…); **aceptación de la política de tratamiento de datos** (obligatoria); **canales autorizados** (Email/SMS/WhatsApp).

Hallazgos:
- Al marcar la aceptación de datos, **SMS y WhatsApp se activan automáticamente**.
- Ficha del propietario: botón **Notas** (alerta importante), editar y panel de mascotas.
- **El buscador muestra propietarios y mascotas de otras clínicas OkVet** (documento enmascarado `****5123`, nombres visibles). Hay un registro global compartido.

**→ Zooetia:**
- Consentimiento de Habeas Data (Ley 1581) **guardado como evidencia**: fecha, usuario, versión de la política.
- **Opt-in por canal explícito**, no automático.
- Tutores **aislados por clínica** (nada de registro global): es un argumento de privacidad para vender.
- Incluir contacto autorizado, "cómo nos encontró" (marketing) y notas importantes.

## 6. Mascotas

Formulario: foto; animal de servicio; animal de apoyo emocional; nombre; código/chip (único, opcional); **especie (14 + otra)**; **raza dependiente de la especie** (canino 235, felino 86, equino 95…, + otra); género (Macho/Hembra/N/D); **color** (catálogo con búsqueda); fecha de nacimiento (edad calculada); peso + unidad (g/lb/kg); **talla** (miniatura → gigante); **estado reproductivo** (esterilizado / no / desconocido); **temperamento** (muy dócil → agresivo, con color); **alimento** (catálogo marca-línea: "Pro Plan - Adulto"); **alertas clínicas por especie** (canino: leishmaniosis, ehrlichiosis, dirofilariosis, brucelosis, rabia; felino: FIV, FeLV, PIF, panleucopenia, calicivirus, herpesvirus). También existe el campo "Fallecido".

"Datos generales" (estilo de vida): cantidad y frecuencia de alimento, vivienda, frecuencia de baño, productos de baño, otras mascotas, último calor.

**→ Zooetia:** adoptar casi todo. Catálogos globales (especies, razas, colores) sembrados por seeder, más valores propios por clínica. Temperamento y alertas se muestran siempre en la cabecera de la mascota (seguridad del personal).

## 7. Historia clínica

Vista por mascota con menú lateral y contadores: Consultas · Vacunaciones · Fórmulas médicas · Desparasitaciones · Hospitalizaciones/ambulatorios · Cirugías/procedimientos · Órdenes · Laboratorio · Imágenes diagnósticas · Peluquería y spa · Guardería · Seguimientos · Documentos · Remisiones · Citas · Tareas · Mensajes al propietario. Cabecera con **"Resumen con IA"**, editar y exportar (PDF).

### Consulta (formato SOIP)
- Fecha, **motivo/tipo** (46), **S** subjetivo (anamnesis), **O** objetivo (examen, lista de problemas), **I** interpretación (diagnóstico presuntivo, diferencial y final), **P** plan terapéutico, **P** plan diagnóstico. Cada texto tiene **"Corregir con IA"**.
- **Examen físico general:** temperatura, peso (con unidades), condición corporal (ICC), TLLC, FC, FR, reflejos, pulso, SpO₂, presión arterial, mucosas (rosadas, pálidas, cianóticas, ictéricas…), glicemia, palpación, turgencia.
- **Examen por sistemas** (No evaluado/Normal/Anormal + observación): actitud, hidratación, estado nutricional, ganglios, mucosas, cardiovascular, respiratorio, digestivo, urinario, reproductivo, musculoesquelético, nervioso, piel y anexos, ojos, oídos.
- Próximo control (dispara recordatorios), adjuntos, **odontograma** y **examen ortopédico** (módulos nuevos).

### Otros registros (campos principales)
| Registro | Campos |
|---|---|
| Vacunación | fecha, vacuna (catálogo), **laboratorio, lote**, observaciones, próxima vacunación |
| Desparasitación | fecha, última desparasitación, tipo (interna/externa), producto, dosis, próximo control, adjunto |
| Fórmula médica | fecha, diagnóstico, medicamentos (varios), observaciones (imprimible) |
| Cirugía/procedimiento | procedimiento (catálogo de 190) u otro, descripción quirúrgica, preanestésico, anestésico, otros medicamentos, tratamiento, complicaciones, adjuntos, seguimiento de anestesia |
| Seguimiento | tipo, motivo, detalles, mismos signos vitales, próximo control |
| Orden / Lab / Imagen | tipo, subtipo, prioridad, pruebas (catálogo de 208), diagnóstico presuntivo, carga de resultados |
| Remisión | profesional, centro destino, razón |
| Hospitalización | tipo, ingreso, salida, motivo de salida, **kardex** de mediciones periódicas |
| Peluquería/spa | servicios, fotos antes/después, próxima cita, antirrábica vigente |
| Documentos | plantillas (consentimiento informado, disentimiento, guardería) con **firma digital por link** |
| Mensaje al propietario | texto, canal, leído |

**→ Zooetia (MVP):** consulta SOIP con signos vitales y examen por sistemas, vacunación (con lote), desparasitación, fórmula médica, procedimiento/cirugía simple, seguimiento, adjuntos y próximo control en todos. **Post-MVP:** hospitalización/kardex, órdenes/lab/imágenes, spa, guardería, odontograma, consentimientos con firma digital e IA (resumen/corrección, un posible diferenciador propio).

## 8. Agenda

- Vistas: Mes, Semana, Día, Lista y **Programador** (línea de tiempo por veterinario, con fila "Sin asignar"). Búsqueda por documento, nombre o celular.
- Crear cita: "Solo reservar el espacio" (bloqueo sin propietario, sin notificaciones), propietario y mascota (con registro rápido), **tipo (10) + subtipo** (34 especialidades / tipo de imagen), **varios encargados**, **sin hora definida**, inicio y fin, título automático, descripción visible para el tutor, a domicilio o en la clínica.
- **Estados:** Pendiente · Confirmada · Asistencia declinada · Asiste · Cancelación · Pre-registro · Facturado. El color del evento depende del estado.
- Desde la cita: "Ir al consultorio" (abre la HC), editar, cambiar estado.

**→ Zooetia:** estados propuestos `scheduled`, `confirmed`, `checked_in` (llegó), `in_progress`, `completed`, `cancelled`, `no_show`. "Facturado" se deriva de la venta, no es un estado de la cita. Guardar el **historial de cambios de estado** (quién, cuándo, por qué canal; p. ej. "confirmada por WhatsApp"). Esa es la base del bot.

## 9. Otros módulos

- **Tareas pendientes:** tarea general o ligada a tutor/paciente, responsable, estado (pendiente/finalizada/descartada).
- **Solicitudes:** solicitudes de HC (compartir con tutor u otra clínica), órdenes internas por tipo y prioridad, solicitudes de cita desde OkPet.
- **Planes de salud** (membresías): especies, duración, servicios incluidos, vigencias, cobertura automática y alertas de vencimiento.
- **Marketing** (solo Pro): campañas de correo con métricas (enviados, fallidos, desuscritos).
- **Informes:** dashboard (ventas vs periodo anterior, meta, servicios por tipo, especies, pacientes/día); más de 20 exportables por tipo de registro; premium (clientes inactivos, calificaciones, uso de recursos, dashboard de ventas).

**→ Zooetia:** tareas en MVP (simples). Planes de salud, marketing y calificaciones van post-MVP. Informe de **clientes inactivos** y **vacunas por vencer** son baratos y valiosos para el Pro con WhatsApp.

## 10. Catálogos precargados

Vacunas (12 comerciales: Nobivac, Feligen, Leucogen…) · Cirugías/procedimientos (190) · Pruebas de laboratorio (208, por categoría) · Motivos de consulta (46) · Servicios (52) · Especies (14) y razas por especie · Colores · Alimentos (marca-línea) · Formatos de documentos (3).

**→ Zooetia:** catálogos **globales** (tabla sin `clinic_id`, solo lectura) más catálogos **propios de la clínica**. Sembrar desde el inicio: especies, razas, colores y vacunas comunes en Colombia.
