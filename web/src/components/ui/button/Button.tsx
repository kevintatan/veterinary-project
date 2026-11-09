import { cn } from "@/utils";
import type { ButtonHTMLAttributes, ReactNode } from "react";

/**
 * TailAdmin button, extended for real forms: native attributes (type, form…)
 * and a loading state that disables the button while a request is in flight.
 */
interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  size?: "sm" | "md";
  variant?: "primary" | "outline" | "danger" | "ghost";
  startIcon?: ReactNode;
  endIcon?: ReactNode;
  loading?: boolean;
}

const sizeClasses = {
  sm: "px-4 py-2.5 text-sm",
  md: "px-5 py-3.5 text-sm",
};

const variantClasses = {
  primary: "bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300",
  outline:
    "bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-transparent dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/5 dark:hover:text-white",
  danger: "bg-error-500 text-white shadow-theme-xs hover:bg-error-600",
  ghost: "text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5",
};

export default function Button({
  children,
  size = "md",
  variant = "primary",
  startIcon,
  endIcon,
  loading = false,
  disabled = false,
  className,
  type = "button",
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={cn(
        "inline-flex items-center justify-center gap-2 rounded-lg font-medium transition",
        sizeClasses[size],
        variantClasses[variant],
        (disabled || loading) && "cursor-not-allowed opacity-60",
        className,
      )}
      {...props}
    >
      {loading ? (
        <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden />
      ) : (
        startIcon && <span className="flex items-center">{startIcon}</span>
      )}
      {children}
      {endIcon && !loading && <span className="flex items-center">{endIcon}</span>}
    </button>
  );
}
