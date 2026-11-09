/**
 * Agenda date helpers. Days are plain "YYYY-MM-DD" strings (clinic calendar days)
 * and times come from API ISO strings that already carry the clinic offset
 * (2026-10-05T09:30:00-05:00), so nothing depends on the browser's timezone.
 */

export type Day = string; // YYYY-MM-DD

const DAY_NAMES = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
const MONTHS = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sept", "oct", "nov", "dic"];
const MONTHS_LONG = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];

function toDate(day: Day): Date {
  return new Date(`${day}T00:00:00Z`);
}

function fromDate(date: Date): Day {
  return date.toISOString().slice(0, 10);
}

export function addDays(day: Day, n: number): Day {
  const d = toDate(day);
  d.setUTCDate(d.getUTCDate() + n);
  return fromDate(d);
}

/** ISO weekday: 1 = Monday … 7 = Sunday (same as the API's schedules). */
export function isoWeekday(day: Day): number {
  const w = toDate(day).getUTCDay();
  return w === 0 ? 7 : w;
}

export function weekStart(day: Day): Day {
  return addDays(day, 1 - isoWeekday(day));
}

export function weekDays(day: Day): Day[] {
  const start = weekStart(day);
  return Array.from({ length: 7 }, (_, i) => addDays(start, i));
}

export function isValidDay(value: string | null): value is Day {
  return !!value && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(toDate(value).getTime());
}

/** "Lun 5" */
export function shortDayLabel(day: Day): string {
  return `${DAY_NAMES[isoWeekday(day) - 1]} ${Number(day.slice(8, 10))}`;
}

/** "lunes, 5 de octubre de 2026" style title for the toolbar. */
export function longDayLabel(day: Day): string {
  const d = toDate(day);
  const weekday = new Intl.DateTimeFormat("es-CO", { weekday: "long", timeZone: "UTC" }).format(d);
  return `${weekday}, ${d.getUTCDate()} de ${MONTHS_LONG[d.getUTCMonth()]} de ${d.getUTCFullYear()}`;
}

/** "5 – 11 oct 2026" */
export function weekLabel(day: Day): string {
  const [first, last] = [weekStart(day), addDays(weekStart(day), 6)];
  const [a, b] = [toDate(first), toDate(last)];
  const sameMonth = a.getUTCMonth() === b.getUTCMonth();

  return `${a.getUTCDate()}${sameMonth ? "" : ` ${MONTHS[a.getUTCMonth()]}`} – ${b.getUTCDate()} ${MONTHS[b.getUTCMonth()]} ${b.getUTCFullYear()}`;
}

/** Calendar day of an API ISO datetime (in the clinic offset it carries). */
export function dayOf(iso: string): Day {
  return iso.slice(0, 10);
}

/** Minutes since midnight of an API ISO datetime, in the clinic offset it carries. */
export function minutesOf(iso: string): number {
  return Number(iso.slice(11, 13)) * 60 + Number(iso.slice(14, 16));
}

/** "HH:MM" (or "HH:MM:SS") → minutes since midnight. */
export function minutesOfTime(time: string): number {
  return Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
}

/** 570 → "9:30 a. m." */
export function formatMinutes(minutes: number): string {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  const suffix = h < 12 ? "a. m." : "p. m.";
  const h12 = h % 12 === 0 ? 12 : h % 12;

  return `${h12}:${String(m).padStart(2, "0")} ${suffix}`;
}

/** Local clinic datetime for the API (no offset → interpreted in the clinic timezone). */
export function toLocalDateTime(day: Day, minutes: number): string {
  return `${day}T${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`;
}

/**
 * Side-by-side lanes for overlapping items in one column (when the clinic
 * allows overlaps). Returns lane index and lane count per item.
 */
export function layoutLanes<T extends { start: number; end: number }>(items: T[]): (T & { lane: number; lanes: number })[] {
  const sorted = [...items].sort((a, b) => a.start - b.start || b.end - a.end);
  const result: (T & { lane: number; lanes: number })[] = [];
  let cluster: (T & { lane: number; lanes: number })[] = [];
  let clusterEnd = -1;

  const flush = () => {
    const lanes = Math.max(1, ...cluster.map((c) => c.lane + 1));
    cluster.forEach((c) => result.push({ ...c, lanes }));
    cluster = [];
  };

  for (const item of sorted) {
    if (item.start >= clusterEnd && cluster.length > 0) {
      flush();
    }

    const taken = new Set(cluster.filter((c) => c.end > item.start).map((c) => c.lane));
    let lane = 0;
    while (taken.has(lane)) lane++;

    cluster.push({ ...item, lane, lanes: 1 });
    clusterEnd = Math.max(clusterEnd, item.end);
  }

  flush();

  return result;
}

/** First day of the month of `day`. */
export function monthStart(day: Day): Day {
  return `${day.slice(0, 7)}-01`;
}

/** Same day-of-month n months away (clamped: 31 ene + 1 → 28/29 feb). */
export function addMonths(day: Day, n: number): Day {
  const d = toDate(monthStart(day));
  d.setUTCMonth(d.getUTCMonth() + n);
  const last = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0)).getUTCDate();
  d.setUTCDate(Math.min(Number(day.slice(8, 10)), last));
  return fromDate(d);
}

/** 6 full weeks (Mon–Sun) covering the month of `day`: 42 days. */
export function monthGridDays(day: Day): Day[] {
  const start = weekStart(monthStart(day));
  return Array.from({ length: 42 }, (_, i) => addDays(start, i));
}

/** "octubre de 2026" */
export function monthLabel(day: Day): string {
  return `${MONTHS_LONG[Number(day.slice(5, 7)) - 1]} de ${day.slice(0, 4)}`;
}

export const WEEKDAY_SHORT = DAY_NAMES;
