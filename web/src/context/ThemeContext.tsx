"use client";

import { createContext, useCallback, useContext, useSyncExternalStore } from "react";

type ThemeMode = "light" | "dark" | "auto";
type ResolvedTheme = "light" | "dark";

type ThemeContextType = {
  theme: ResolvedTheme;
  themeMode: ThemeMode;
  setThemeMode: (mode: ThemeMode) => void;
  toggleTheme: () => void;
};

const STORAGE_KEY = "theme-mode";
const CHANGE_EVENT = "zoo-theme-change";
const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

/**
 * Inline script for <head>: applies the saved theme before first paint
 * (no white flash in dark mode). Mirrors resolveTheme() below.
 */
export const THEME_INIT_SCRIPT = `(function(){try{var m=localStorage.getItem("${STORAGE_KEY}")||"light";var d=m==="dark"||(m==="auto"&&matchMedia("(prefers-color-scheme: dark)").matches);document.documentElement.classList.toggle("dark",d);}catch(e){}})();`;

function readMode(): ThemeMode {
  const saved = localStorage.getItem(STORAGE_KEY);
  return saved === "dark" || saved === "auto" ? saved : "light";
}

function prefersDark(): boolean {
  return window.matchMedia("(prefers-color-scheme: dark)").matches;
}

function subscribe(onChange: () => void): () => void {
  const media = window.matchMedia("(prefers-color-scheme: dark)");
  window.addEventListener("storage", onChange);
  window.addEventListener(CHANGE_EVENT, onChange);
  media.addEventListener("change", onChange);

  return () => {
    window.removeEventListener("storage", onChange);
    window.removeEventListener(CHANGE_EVENT, onChange);
    media.removeEventListener("change", onChange);
  };
}

function applyToDocument(theme: ResolvedTheme): void {
  document.documentElement.classList.toggle("dark", theme === "dark");
  document.documentElement.setAttribute("data-color-scheme", theme);
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const themeMode = useSyncExternalStore(subscribe, readMode, () => "light" as ThemeMode);
  const systemDark = useSyncExternalStore(subscribe, prefersDark, () => false);
  const theme: ResolvedTheme = themeMode === "auto" ? (systemDark ? "dark" : "light") : themeMode;

  const setThemeMode = useCallback((mode: ThemeMode) => {
    localStorage.setItem(STORAGE_KEY, mode);
    applyToDocument(mode === "auto" ? (prefersDark() ? "dark" : "light") : mode);
    window.dispatchEvent(new Event(CHANGE_EVENT));
  }, []);

  const toggleTheme = useCallback(() => setThemeMode(theme === "light" ? "dark" : "light"), [setThemeMode, theme]);

  return <ThemeContext.Provider value={{ theme, themeMode, setThemeMode, toggleTheme }}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeContextType {
  const context = useContext(ThemeContext);
  if (context === undefined) {
    throw new Error("useTheme must be used within a ThemeProvider");
  }
  return context;
}
