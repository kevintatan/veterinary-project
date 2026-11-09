"use client";

import { useTheme } from "@/context/ThemeContext";

/**
 * Chart tokens per mode (docs/design.md + dataviz method). Dark values are their
 * own validated steps against the dark card surface, not an automatic flip.
 *
 * Validated with dataviz/scripts/validate_palette.js:
 *  - light  #166534 + #f97066 on #ffffff: all checks pass (CVD ΔE 15.5); coral < 3:1
 *           contrast → relief = legend + table view (both shipped).
 *  - dark   #3fa66a + #d95926 on #161f2e: no FAIL; CVD ΔE 7.5 (6–8 band) → requires
 *           secondary encoding = 2px surface gaps + legend + table view (shipped).
 */
const TOKENS = {
  light: {
    surface: "#ffffff",
    grid: "#f2f4f7",
    axis: "#667085",
    text: "#101828",
    attended: "#166534",
    missed: "#f97066",
    single: "#166534",
  },
  dark: {
    surface: "#161f2e",
    grid: "#1d2939",
    axis: "#98a2b3",
    text: "#f2f4f7",
    attended: "#3fa66a",
    missed: "#d95926",
    single: "#3fa66a",
  },
} as const;

export type ChartTokens = (typeof TOKENS)["light" | "dark"];

export function useChartTokens(): ChartTokens & { mode: "light" | "dark" } {
  const { theme } = useTheme();

  return { ...TOKENS[theme], mode: theme };
}

export const FONT_FAMILY = "var(--font-jakarta), ui-sans-serif, system-ui, sans-serif";
