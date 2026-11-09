import { cn } from "@/utils";

/** TailAdmin card: 16 px radius, thin border, no shadow (docs/design.md). */
export function Card({ className, children }: { className?: string; children: React.ReactNode }) {
  return (
    <section className={cn("rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3", className)}>
      {children}
    </section>
  );
}

export function CardHeader({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) {
  return (
    <header className="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
      <div>
        <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">{title}</h2>
        {description && <p className="mt-0.5 text-theme-sm text-gray-500 dark:text-gray-400">{description}</p>}
      </div>
      {action}
    </header>
  );
}

export function EmptyState({ title, description }: { title: string; description?: string }) {
  return (
    <div className="px-5 py-10 text-center">
      <p className="text-theme-sm font-medium text-gray-700 dark:text-gray-300">{title}</p>
      {description && <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">{description}</p>}
    </div>
  );
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn("animate-pulse rounded-lg bg-gray-100 dark:bg-white/5", className)} />;
}
