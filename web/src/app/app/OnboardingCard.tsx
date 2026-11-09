"use client";

import { useCan, useSession } from "@/components/providers/SessionProvider";
import { api } from "@/lib/api";
import { cn } from "@/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useSearchParams } from "next/navigation";

type Onboarding = {
  steps: { key: string; label: string; hint: string; href: string; done: boolean }[];
  done: number;
  total: number;
  visible: boolean;
};

/**
 * "Primeros pasos" for a new clinic (admins only). Each step ticks itself off
 * with real data; the card disappears when everything is done or dismissed.
 */
export default function OnboardingCard() {
  const isAdmin = useCan("clinic.settings.manage");
  const { user, clinic } = useSession();
  const welcome = useSearchParams().get("bienvenida") === "1";
  const queryClient = useQueryClient();

  const onboarding = useQuery({
    queryKey: ["onboarding"],
    queryFn: () => api<{ data: Onboarding }>("onboarding").then((r) => r.data),
    enabled: isAdmin,
  });
  const dismiss = useMutation({
    mutationFn: () => api<{ data: Onboarding }>("onboarding/dismiss", { method: "POST" }).then((r) => r.data),
    onSuccess: (d) => queryClient.setQueryData(["onboarding"], d),
  });

  const o = onboarding.data;
  if (!isAdmin || !o?.visible) return null;

  const percent = Math.round((o.done / o.total) * 100);

  return (
    <section className="mb-6 overflow-hidden rounded-2xl border border-brand-200 bg-white dark:border-brand-500/30 dark:bg-gray-900">
      <header className="flex flex-wrap items-start justify-between gap-3 bg-brand-25 px-5 py-4 dark:bg-brand-500/10">
        <div>
          <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">
            {welcome ? `¡Te damos la bienvenida a Zooetia, ${user.name.split(" ")[0]}!` : "Primeros pasos"}
          </h2>
          <p className="text-theme-sm text-gray-600 dark:text-gray-300">
            {welcome ? `${clinic.name} ya está creada y tienes 15 días gratis con todas las funciones. ` : ""}
            Deja lista tu clínica en unos minutos: {o.done} de {o.total} pasos.
          </p>
        </div>
        <button type="button" onClick={() => dismiss.mutate()} disabled={dismiss.isPending} className="text-theme-xs font-medium text-gray-500 hover:text-gray-800 dark:hover:text-white">
          Ocultar
        </button>
      </header>
      <div className="h-1.5 bg-gray-100 dark:bg-white/5" aria-hidden>
        <div className="h-full bg-brand-500 transition-[width] duration-500" style={{ width: `${percent}%` }} />
      </div>
      <ol className="grid gap-px bg-gray-100 sm:grid-cols-2 lg:grid-cols-3 dark:bg-gray-800">
        {o.steps.map((s) => (
          <li key={s.key} className="bg-white dark:bg-gray-900">
            <Link href={s.href} className={cn("flex h-full items-start gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-white/2", s.done && "opacity-60")}>
              <span
                className={cn(
                  "mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold",
                  s.done ? "border-brand-500 bg-brand-500 text-white" : "border-gray-300 dark:border-gray-600",
                )}
                aria-hidden
              >
                {s.done && "✓"}
              </span>
              <span className="min-w-0">
                <span className={cn("block text-theme-sm font-medium text-gray-800 dark:text-white/90", s.done && "line-through")}>{s.label}</span>
                <span className="block text-theme-xs text-gray-500">{s.hint}</span>
              </span>
            </Link>
          </li>
        ))}
      </ol>
    </section>
  );
}
