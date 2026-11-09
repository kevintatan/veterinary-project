import { cn } from "@/utils";

/**
 * Plain form primitives with the TailAdmin look (44 px fields, 8 px radius,
 * brand focus ring). Native inputs: they work with any form state and keyboard.
 */
export const inputClass =
  "h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden disabled:bg-gray-50 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-gray-500 dark:disabled:bg-white/3";

export function Field({
  label,
  htmlFor,
  hint,
  error,
  optional,
  className,
  children,
}: {
  label: string;
  htmlFor?: string;
  hint?: string;
  error?: string;
  optional?: boolean;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <div className={className}>
      <label htmlFor={htmlFor} className="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-400">
        {label}
        {optional && <span className="font-normal text-gray-400"> (opcional)</span>}
      </label>
      {children}
      {error ? (
        <p className="mt-1 text-theme-xs text-error-500">{error}</p>
      ) : (
        hint && <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{hint}</p>
      )}
    </div>
  );
}

export function Input({ className, invalid, ...props }: React.InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }) {
  return (
    <input
      {...props}
      aria-invalid={invalid || undefined}
      className={cn(inputClass, invalid && "border-error-300 focus:border-error-300 focus:ring-error-500/10", className)}
    />
  );
}

export function Select({ className, invalid, children, ...props }: React.SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }) {
  return (
    <select {...props} aria-invalid={invalid || undefined} className={cn(inputClass, "pe-8", invalid && "border-error-300", className)}>
      {children}
    </select>
  );
}

// ComponentProps includes `ref` (React 19 passes it as a prop): editors insert text at the cursor.
export function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return <textarea {...props} className={cn(inputClass, "h-auto py-2.5", className)} />;
}

export function Checkbox({ label, description, ...props }: React.InputHTMLAttributes<HTMLInputElement> & { label: React.ReactNode; description?: React.ReactNode }) {
  return (
    <label className="flex cursor-pointer items-start gap-3 text-theme-sm">
      <input type="checkbox" {...props} className="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 accent-brand-500" />
      <span>
        <span className="text-gray-800 dark:text-white/90">{label}</span>
        {description && <span className="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">{description}</span>}
      </span>
    </label>
  );
}

/** Section title inside long forms. */
export function FormSection({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="space-y-4 border-t border-gray-100 pt-5 first:border-t-0 first:pt-0 dark:border-gray-800">
      <h3 className="text-theme-xs font-semibold tracking-wide text-gray-400 uppercase">{title}</h3>
      {children}
    </section>
  );
}
