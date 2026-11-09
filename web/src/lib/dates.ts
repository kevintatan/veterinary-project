/**
 * Dates are shown in the clinic timezone (the API already returns ISO with
 * the clinic offset, e.g. 2026-10-05T09:00:00-05:00).
 */

/** YYYY-MM-DD of "today" in the clinic timezone. */
export function todayIn(timeZone: string, date: Date = new Date()): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone, year: "numeric", month: "2-digit", day: "2-digit" }).format(date);
}

export function formatTime(iso: string, timeZone: string): string {
  return new Intl.DateTimeFormat("es-CO", { timeZone, hour: "numeric", minute: "2-digit", hour12: true }).format(new Date(iso));
}

/** "lunes, 5 de octubre" */
export function formatLongDate(timeZone: string, date: Date = new Date()): string {
  return new Intl.DateTimeFormat("es-CO", { timeZone, weekday: "long", day: "numeric", month: "long" }).format(date);
}

export function greeting(timeZone: string, date: Date = new Date()): string {
  const hour = Number(new Intl.DateTimeFormat("en-US", { timeZone, hour: "numeric", hourCycle: "h23" }).format(date));

  return hour < 12 ? "Buenos días" : hour < 19 ? "Buenas tardes" : "Buenas noches";
}
