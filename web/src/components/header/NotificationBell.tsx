"use client";

import { api } from "@/lib/api";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";

type Notification = { id: string; type: string; title: string; body: string | null; url: string | null; read: boolean; created_at: string };
type Feed = { data: Notification[]; unread: number };

const KEY = ["notifications"] as const;

/** Every 20 s while the tab is visible (paused in the background), and right away on coming back. */
const POLL_MS = 20_000;

/** Icon per kind of notification (docs/notificaciones.md). */
const ICON: Record<string, string> = {
  booking_request: "📅",
  task_assigned: "✅",
  document_signed: "✍️",
  whatsapp_reply: "💬",
  inventory_alert: "📦",
};

const COUNT_PREFIX = /^\(\d+\+?\) /;

function ago(iso: string): string {
  const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
  if (minutes < 1) return "ahora";
  if (minutes < 60) return `hace ${minutes} min`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `hace ${hours} h`;
  const days = Math.round(hours / 24);
  return days === 1 ? "ayer" : `hace ${days} días`;
}

/** A soft two-note chime, generated (no audio file). Browsers allow it after the first click on the page. */
function chime() {
  try {
    const ctx = new AudioContext();
    [880, 1320].forEach((freq, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = "sine";
      osc.frequency.value = freq;
      const at = ctx.currentTime + i * 0.14;
      gain.gain.setValueAtTime(0.0001, at);
      gain.gain.exponentialRampToValueAtTime(0.18, at + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.35);
      osc.connect(gain).connect(ctx.destination);
      osc.start(at);
      osc.stop(at + 0.4);
    });
    setTimeout(() => ctx.close(), 1000);
  } catch {
    // No audio available: the pop-up and the counter are enough.
  }
}

/** "(3) Agenda · Zooetia": the unread count in the tab title, kept when Next.js changes the title. */
function useTitleCounter(unread: number) {
  useEffect(() => {
    const apply = () => {
      const base = document.title.replace(COUNT_PREFIX, "");
      const next = unread > 0 ? `(${unread > 9 ? "9+" : unread}) ${base}` : base;
      if (document.title !== next) document.title = next;
    };
    apply();
    const observer = new MutationObserver(apply);
    observer.observe(document.head, { childList: true, characterData: true, subtree: true });
    return () => {
      observer.disconnect();
      document.title = document.title.replace(COUNT_PREFIX, "");
    };
  }, [unread]);
}

/**
 * The bell: what needs my attention in this clinic (docs/notificaciones.md).
 * No need to refresh: it checks every 20 s while the tab is visible and when
 * coming back to it. Something new → pop-up, chime, count in the tab title
 * and, if the person turned them on, a desktop notification.
 * Clicking a notification marks it read and opens where it points.
 */
export default function NotificationBell() {
  const [open, setOpen] = useState(false);
  const [toasts, setToasts] = useState<Notification[]>([]);
  // Only shown inside the dropdown (opened on the client), so reading it at creation is hydration-safe.
  const [desktop, setDesktop] = useState<NotificationPermission | "unsupported">(() =>
    typeof window !== "undefined" && "Notification" in window ? window.Notification.permission : "unsupported",
  );
  const box = useRef<HTMLDivElement>(null);
  const seen = useRef<Set<string> | null>(null);
  const router = useRouter();
  const queryClient = useQueryClient();

  const feed = useQuery({ queryKey: KEY, queryFn: () => api<Feed>("notifications"), refetchInterval: POLL_MS, refetchOnWindowFocus: true });
  const markRead = useMutation({
    mutationFn: (id: string) => api<Feed>(`notifications/${id}/read`, { method: "POST" }),
    onSuccess: (d) => queryClient.setQueryData(KEY, d),
  });
  const markAll = useMutation({
    mutationFn: () => api<Feed>("notifications/read-all", { method: "POST" }),
    onSuccess: (d) => queryClient.setQueryData(KEY, d),
  });

  const dismiss = (id: string) => setToasts((t) => t.filter((x) => x.id !== id));
  const go = (n: Notification) => {
    if (!n.read) markRead.mutate(n.id);
    setOpen(false);
    dismiss(n.id);
    if (n.url) router.push(n.url);
  };

  // What arrived since the last check (not what was already there when the page opened).
  const data = feed.data;
  useEffect(() => {
    if (!data) return;
    if (seen.current === null) {
      seen.current = new Set(data.data.map((n) => n.id));
      return;
    }
    const known = seen.current;
    const fresh = data.data.filter((n) => !n.read && !known.has(n.id)).slice(0, 3);
    data.data.forEach((n) => known.add(n.id));
    if (!fresh.length) return;

    // Counters that depend on the same events (e.g. pending online requests) refresh too.
    queryClient.invalidateQueries({ queryKey: ["booking-requests"] });
    queryClient.invalidateQueries({ queryKey: ["agenda"] });
    chime();
    setToasts((t) => [...fresh, ...t].slice(0, 3));
    fresh.forEach((n) => setTimeout(() => setToasts((t) => t.filter((x) => x.id !== n.id)), 8000));

    if ("Notification" in window && window.Notification.permission === "granted" && document.visibilityState !== "visible") {
      fresh.forEach((n) => {
        const shown = new window.Notification(n.title, { body: n.body ?? undefined, tag: n.id, icon: "/favicon.ico" });
        shown.onclick = () => {
          window.focus();
          if (n.url) router.push(n.url);
          shown.close();
        };
      });
    }
  }, [data, queryClient, router]);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => box.current && !box.current.contains(e.target as Node) && setOpen(false);
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("mousedown", onDown);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDown);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const unread = data?.unread ?? 0;
  const items = data?.data ?? [];
  useTitleCounter(unread);

  return (
    <div ref={box} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-label={unread ? `Notificaciones, ${unread} sin leer` : "Notificaciones"}
        aria-expanded={open}
        className="relative flex size-11 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
      >
        <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          <path d="M6 9a6 6 0 1 1 12 0c0 4.5 1.8 6.3 2.5 7H3.5C4.2 15.3 6 13.5 6 9Z" />
          <path d="M10 19.5a2.2 2.2 0 0 0 4 0" />
        </svg>
        {unread > 0 && (
          <span className="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-error-500 px-1 text-[11px] font-semibold text-white ring-2 ring-white dark:ring-gray-900">
            {unread > 9 ? "9+" : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 z-50 mt-2 w-[min(380px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
          <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
            <p className="text-sm font-semibold text-gray-800 dark:text-white/90">Notificaciones</p>
            {unread > 0 && (
              <button type="button" onClick={() => markAll.mutate()} className="text-theme-xs font-medium text-brand-600 hover:underline">
                Marcar todas como leídas
              </button>
            )}
          </div>
          {items.length === 0 ? (
            <p className="px-4 py-10 text-center text-theme-sm text-gray-500">No tienes notificaciones.</p>
          ) : (
            <ul className="max-h-[420px] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
              {items.map((n) => (
                <li key={n.id}>
                  <button
                    type="button"
                    onClick={() => go(n)}
                    className={cn("flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/5", !n.read && "bg-brand-25 dark:bg-brand-500/5")}
                  >
                    <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm dark:bg-white/5" aria-hidden>
                      {ICON[n.type] ?? "🔔"}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className={cn("block text-theme-sm text-gray-800 dark:text-white/90", !n.read && "font-semibold")}>{n.title}</span>
                      {n.body && <span className="mt-0.5 block text-theme-xs text-gray-500">{n.body}</span>}
                      <span className="mt-1 block text-[11px] text-gray-400">{ago(n.created_at)}</span>
                    </span>
                    {!n.read && <span className="mt-2 size-2 shrink-0 rounded-full bg-brand-500" aria-label="Sin leer" />}
                  </button>
                </li>
              ))}
            </ul>
          )}
          {desktop === "default" && (
            <button
              type="button"
              onClick={() => window.Notification.requestPermission().then(setDesktop)}
              className="w-full border-t border-gray-100 px-4 py-2.5 text-theme-xs font-medium text-brand-600 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5"
            >
              🔔 Avisarme en el escritorio aunque esté en otra ventana
            </button>
          )}
          {desktop === "denied" && (
            <p className="border-t border-gray-100 px-4 py-2.5 text-center text-theme-xs text-gray-400 dark:border-gray-800">Los avisos de escritorio están bloqueados en este navegador.</p>
          )}
        </div>
      )}

      {/* Pop-ups for what just arrived */}
      <div className="pointer-events-none fixed right-4 bottom-4 z-[100000] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-2" aria-live="polite">
        {toasts.map((n) => (
          <div
            key={n.id}
            className="pointer-events-auto flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-3.5 shadow-theme-lg motion-safe:animate-[fade-up_.25s_ease-out] dark:border-gray-700 dark:bg-gray-900"
          >
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm dark:bg-brand-500/15" aria-hidden>
              {ICON[n.type] ?? "🔔"}
            </span>
            <button type="button" onClick={() => go(n)} className="min-w-0 flex-1 text-left">
              <span className="block text-theme-sm font-semibold text-gray-800 dark:text-white/90">{n.title}</span>
              {n.body && <span className="mt-0.5 block text-theme-xs text-gray-500">{n.body}</span>}
            </button>
            <button type="button" onClick={() => dismiss(n.id)} aria-label="Cerrar aviso" className="px-1 text-lg leading-none text-gray-400 hover:text-gray-700">
              ×
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
