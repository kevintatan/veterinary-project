import { cn } from "@/utils";

/**
 * File-with-folded-corner + red "PDF" label: the convention people already
 * recognize for "this downloads a PDF".
 */
export default function PdfIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 20 20" fill="none" aria-hidden className={cn("h-4.5 w-4.5 shrink-0", className)}>
      <path d="M5 2.75h6.5L15.25 6.5v10a.75.75 0 0 1-.75.75h-9.5a.75.75 0 0 1-.75-.75V3.5A.75.75 0 0 1 5 2.75Z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" />
      <path d="M11.25 2.75V6.5h4" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" />
      <rect x="1.75" y="9.5" width="12" height="6" rx="1.25" fill="#D92D20" />
      <text x="7.75" y="14.15" textAnchor="middle" fontSize="4.6" fontWeight="700" fill="#fff" fontFamily="ui-sans-serif, system-ui, sans-serif" letterSpacing=".2">
        PDF
      </text>
    </svg>
  );
}
