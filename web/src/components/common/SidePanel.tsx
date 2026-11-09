"use client";

import { useEffect, useRef } from "react";

/**
 * Right-side panel (forms and details). The page stays visible behind it;
 * Escape or a click on the backdrop closes it.
 */
export default function SidePanel({
  title,
  description,
  onClose,
  footer,
  children,
  width = "max-w-lg",
}: {
  title: React.ReactNode;
  description?: React.ReactNode;
  onClose: () => void;
  footer?: React.ReactNode;
  children: React.ReactNode;
  width?: string;
}) {
  const panel = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    document.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    // Focus the panel (keyboard users land inside it), unless a field inside already took it
    // with autoFocus: stealing it would swallow the first keystrokes.
    if (!panel.current?.contains(document.activeElement)) panel.current?.focus();
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-99999" role="dialog" aria-modal="true" aria-labelledby="side-panel-title">
      <div className="absolute inset-0 animate-[fadeIn_150ms_ease-out] bg-gray-900/30 backdrop-blur-[2px]" onClick={onClose} aria-hidden />
      <div
        ref={panel}
        tabIndex={-1}
        className={`absolute inset-y-0 right-0 flex w-full ${width} animate-[slideIn_200ms_ease-out] flex-col bg-white shadow-theme-xl outline-none dark:bg-gray-900`}
      >
        <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5 dark:border-gray-800">
          <div className="min-w-0">
            <h2 id="side-panel-title" className="text-lg font-semibold text-gray-900 dark:text-white">
              {title}
            </h2>
            {description && <p className="mt-0.5 text-theme-sm text-gray-500 dark:text-gray-400">{description}</p>}
          </div>
          <button type="button" onClick={onClose} aria-label="Cerrar" className="-me-2 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-200">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden>
              <path d="M5 5l10 10M15 5 5 15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            </svg>
          </button>
        </div>
        <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>
        {footer && <div className="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{footer}</div>}
      </div>
    </div>
  );
}
