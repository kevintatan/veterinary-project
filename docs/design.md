# Sistema de diseño — Zooetia (dashboard web)

> Decidido el 2026-10-01. Fuente de verdad visual para `web/`. Los tokens viven en un solo archivo CSS (`@theme` de Tailwind 4): cambiar la marca = cambiar ese archivo.

## 1. Base

- **Código base:** [TailAdmin Free — Next.js](https://github.com/TailAdmin/free-nextjs-admin-dashboard) (**licencia MIT**: se conserva su aviso en `web/LICENSES/tailadmin.txt`). Solo la versión gratuita; los componentes *Pro* de su demo **no** se copian.
- **Stack:** Next.js (App Router) + React 19 + TypeScript + Tailwind CSS 4, igual que TailAdmin.
- Referencia visual del demo: https://nextjs-demo.tailadmin.com/

## 2. Tokens

### Tipografía
- **Plus Jakarta Sans** (Google Fonts, vía `next/font`). Números **tabulares** (`font-variant-numeric: tabular-nums`) en horas, precios, pesos y tablas.
- Escala (de TailAdmin): título de página 20–24 px semibold · título de tarjeta 16 px semibold · cuerpo 14 px · etiqueta 14 px medium · texto secundario 12–14 px.

### Color de marca: verde bosque + coral
| Token | Valor | Uso |
|---|---|---|
| `brand-25` | `#F5FBF7` | fondos muy sutiles |
| `brand-50` | `#ECFDF3` | ítem activo del menú, fondos de selección |
| `brand-100` | `#D3F5E0` | hover suave |
| `brand-300` | `#7FCFA0` | borde de foco de campos |
| `brand-500` | `#166534` | **color principal**: botones, enlaces, ítem activo |
| `brand-600` | `#14532D` | hover/pressed del principal |
| `brand-700` | `#0F3F22` | texto sobre `brand-50` |
| `accent-500` | `#F97066` | **coral**: acentos y llamados de atención no críticos |
| `accent-50` | `#FEF3F2` | fondo de acento |

### Neutros y superficies (de TailAdmin)
| Token | Claro | Oscuro |
|---|---|---|
| Fondo de página | `#F9FAFB` | `#101828` |
| Tarjeta | `#FFFFFF` + borde `#E4E7EC` | blanco 3 % + borde `#1D2939` |
| Texto principal | `#101828` | `#FFFFFF` 90 % |
| Texto secundario | `#667085` | `#98A2B3` |
| Etiquetas | `#344054` | `#D0D5DD` |

### Semánticos
Éxito `#12B76A` · Advertencia `#F79009` · Error `#F04438` · Info `#0BA5EC`.

### Estados de cita (fijos en todo el sistema)
| Estado | Color | Nota |
|---|---|---|
| Programada | gris `#667085` | |
| Confirmada | verde marca `#166534` | |
| En sala de espera | ámbar `#F79009` | llegó, esperando |
| En atención | azul `#0BA5EC` | |
| Atendida | verde éxito `#12B76A` | |
| Cancelada | rojo `#F04438` | tachada en la agenda |
| No asistió | coral `#F97066` | |

### Forma
- Tarjetas: radio **16 px**, **borde fino y sin sombra**, padding 20–24 px.
- Campos y botones: alto **44 px**, radio **8 px**, sombra mínima `0 1px 2px rgba(16,24,40,.05)`, anillo de foco `brand-500/10` de 3 px.
- Menú lateral: 290 px (colapsable a íconos), ítem activo con fondo `brand-50` y texto `brand-500`.
- Modo oscuro desde el día 1 (clase `dark`).

## 2b. Gráficos (decidido 2026-10-01)

- Librería: **ApexCharts** (la de TailAdmin). Tokens por modo en `web/src/components/charts/chartTheme.ts`.
- Paleta validada con el script del método dataviz (no a ojo):
  - Claro: atendidas `#166534` + perdidas `#F97066` sobre `#FFFFFF` → pasa todo (CVD ΔE 15,5); el coral queda < 3:1 de contraste → **alivio obligatorio: leyenda + vista de tabla**.
  - Oscuro: `#3FA66A` + `#D95926` sobre `#161F2E` → sin fallas; CVD ΔE 7,5 (franja 6–8) → **codificación secundaria obligatoria: separación de 2 px + leyenda + tabla**.
- Marcas: columnas ≤ 24 px (18 px), punta redondeada 4 px, separación de 2 px en el color de fondo, grilla fina continua, un solo eje Y.
- Cada gráfico tiene **"Ver tabla"**, un filtro de periodo encima que aplica a toda la sección, y al recargar mantiene el gráfico anterior atenuado (sin esqueleto).
- Solo gráficos que respondan una pregunta de la clínica: citas atendidas o perdidas por día, servicios más agendados e indicadores (atendidas, tasa de inasistencia, pacientes nuevos, cancelaciones) contra el periodo anterior.

## 3. Reglas para que no parezca "hecho por IA"

1. **Ningún gráfico de relleno.** Cada gráfico o indicador responde una pregunta concreta de la clínica (ver §2b).
2. **El inicio es "Hoy en la clínica"**: citas del día por veterinario, sala de espera, tareas pendientes y vacunas por vencer. No "Ventas mensuales": de dinero, solo una franja corta "Recaudado hoy" para quien cobra (el resto vive en Reportes).
3. **Componentes del dominio**, no genéricos:
   - **Cabecera de paciente:** foto o ícono de especie, nombre, especie y raza, edad, peso y **alertas siempre visibles** (alergias en rojo, comportamiento en ámbar).
   - **Línea de tiempo clínica** con un ícono por tipo de registro, los anulados tachados y "cerrado" con candado.
   - **Agenda por veterinario** (columnas por profesional, vista de día y de semana) con los colores de estado de la tabla anterior.
4. **Densidad empresarial:** tablas compactas, acciones al alcance (sin modales innecesarios), atajos de teclado (⌘K para buscar tutor o mascota, `N` para nueva cita).
5. **Lenguaje de la clínica:** "Tutor", no "Cliente"; "Atendida", no "Completed". Textos cortos, en español de Colombia, sin emojis.
6. **Consistencia antes que creatividad:** si un patrón ya existe en el sistema, se reutiliza; no se inventa una variante por pantalla.
