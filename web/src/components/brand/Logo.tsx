import { cn } from "@/utils";

/**
 * Provisional Zooetia mark (no official logo yet — info.md §13).
 * Replace this file when the brand is defined.
 */
export function LogoMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" aria-hidden="true" className={cn("h-8 w-8 shrink-0", className)}>
      <rect width="32" height="32" rx="8" className="fill-brand-500" />
      <g className="fill-white">
        <ellipse cx="10" cy="12.2" rx="2.3" ry="2.9" />
        <ellipse cx="14.4" cy="9" rx="2.3" ry="2.9" />
        <ellipse cx="19.6" cy="9" rx="2.3" ry="2.9" />
        <ellipse cx="24" cy="12.2" rx="2.3" ry="2.9" />
        <path d="M17 14.6c-3.6 0-7 4.3-7 7.2 0 2 1.5 2.9 3.2 2.9 1.4 0 2.4-.7 3.8-.7s2.4.7 3.8.7c1.7 0 3.2-.9 3.2-2.9 0-2.9-3.4-7.2-7-7.2Z" />
      </g>
    </svg>
  );
}

export function Logo({ className, inverted = false }: { className?: string; inverted?: boolean }) {
  return (
    <span className={cn("inline-flex items-center gap-2.5", className)}>
      <LogoMark />
      <span
        className={cn(
          "text-xl font-bold tracking-tight",
          inverted ? "text-white" : "text-gray-900 dark:text-white",
        )}
      >
        Zooetia
      </span>
    </span>
  );
}
