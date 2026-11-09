/** Line icons for the landing page (24×24, stroke = currentColor). */
type P = { className?: string };

function Svg({ className, children }: P & { children: React.ReactNode }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden className={className ?? "size-6"}>
      {children}
    </svg>
  );
}

export const CalendarIcon = (p: P) => (
  <Svg {...p}>
    <rect x="3" y="4.5" width="18" height="16" rx="2.5" />
    <path d="M3 9.5h18M8 2.5v4M16 2.5v4M7.5 13.5h3M7.5 17h6" />
  </Svg>
);
export const ClipboardIcon = (p: P) => (
  <Svg {...p}>
    <rect x="5" y="4" width="14" height="17" rx="2.5" />
    <path d="M9 4V3h6v1M9 10h6M9 13.5h6M9 17h3.5" />
  </Svg>
);
export const ChatIcon = (p: P) => (
  <Svg {...p}>
    <path d="M20.5 11.6a8.4 8.4 0 0 1-12.2 7.5L3.5 20.5l1.4-4.6a8.4 8.4 0 1 1 15.6-4.3Z" />
    <path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01" strokeWidth={2.6} />
  </Svg>
);
export const CardIcon = (p: P) => (
  <Svg {...p}>
    <rect x="2.5" y="5" width="19" height="14" rx="2.5" />
    <circle cx="8.5" cy="11" r="2.2" />
    <path d="M5.5 16c.6-1.4 1.7-2.1 3-2.1s2.4.7 3 2.1M14.5 9.5h4M14.5 12.5h4" />
  </Svg>
);
export const PenIcon = (p: P) => (
  <Svg {...p}>
    <path d="M3 20.5c2.5 0 3.5-3 6-3s2.5 2 4.5 2 2.5-1.5 4-1.5" />
    <path d="m14.5 4.5 3 3L9 16H6v-3l8.5-8.5Z" />
  </Svg>
);
export const PortalIcon = (p: P) => (
  <Svg {...p}>
    <rect x="6.5" y="2.5" width="11" height="19" rx="2.5" />
    <path d="M10.5 18.5h3" />
    <path d="M9.5 9.2c0-1.2 1-2.2 2.5-2.2s2.5 1 2.5 2.2c0 2-2.5 3.3-2.5 3.3S9.5 11.2 9.5 9.2Z" />
  </Svg>
);
export const TasksIcon = (p: P) => (
  <Svg {...p}>
    <rect x="3.5" y="3.5" width="17" height="17" rx="3" />
    <path d="m7.5 9 1.5 1.5 3-3M7.5 15.5l1.5 1.5 3-3M14.5 9.5h2.5M14.5 16h2.5" />
  </Svg>
);
export const TeamIcon = (p: P) => (
  <Svg {...p}>
    <circle cx="9" cy="8" r="3.2" />
    <path d="M3 19.5c.8-3.2 3.2-5 6-5s5.2 1.8 6 5" />
    <path d="M16 5.2a3 3 0 0 1 0 5.6M18.2 14.8c1.4.7 2.4 2.3 2.8 4.7" />
  </Svg>
);
export const ShieldIcon = (p: P) => (
  <Svg {...p}>
    <path d="M12 2.8 4.5 5.6v5.6c0 4.8 3.2 8.4 7.5 10 4.3-1.6 7.5-5.2 7.5-10V5.6L12 2.8Z" />
    <path d="m8.8 12 2.3 2.3 4.2-4.6" />
  </Svg>
);
export const CheckIcon = ({ className }: P) => (
  <svg viewBox="0 0 20 20" aria-hidden className={className ?? "size-5"}>
    <circle cx="10" cy="10" r="10" className="fill-brand-100" />
    <path d="m6 10.2 2.6 2.6L14.2 7" fill="none" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" className="stroke-brand-600" />
  </svg>
);
export const ArrowIcon = (p: P) => (
  <Svg {...p}>
    <path d="M5 12h14M13 6l6 6-6 6" />
  </Svg>
);
