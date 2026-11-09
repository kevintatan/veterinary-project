/** Small color helpers for the pet card templates (any color the clinic picks must stay readable). */

function rgb(hex: string): [number, number, number] {
  const h = hex.replace("#", "");
  return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
}

function toHex([r, g, b]: [number, number, number]): string {
  return `#${[r, g, b].map((v) => Math.round(Math.max(0, Math.min(255, v))).toString(16).padStart(2, "0")).join("")}`;
}

/** Mix two colors: t = 0 → a, t = 1 → b. */
export function mix(a: string, b: string, t: number): string {
  const [x, y] = [rgb(a), rgb(b)];
  return toHex([x[0] + (y[0] - x[0]) * t, x[1] + (y[1] - x[1]) * t, x[2] + (y[2] - x[2]) * t]);
}

export const tint = (color: string, t: number) => mix(color, "#ffffff", t);
export const shade = (color: string, t: number) => mix(color, "#000000", t);

export function alpha(hex: string, a: number): string {
  const [r, g, b] = rgb(hex);
  return `rgba(${r}, ${g}, ${b}, ${a})`;
}

/** Relative luminance (WCAG). */
function luminance(hex: string): number {
  const [r, g, b] = rgb(hex).map((v) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Text color to put on top of `background`: white on dark colors, ink on light ones. */
export function onColor(background: string): string {
  return luminance(background) > 0.45 ? "#111827" : "#ffffff";
}

/** A dark version of the color for text on white (keeps contrast even with light picks). */
export function ink(color: string): string {
  return luminance(color) > 0.2 ? shade(color, 0.55) : color;
}
