import { cn } from "@/utils";

const TINT: Record<string, string> = {
  canine: "bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400",
  feline: "bg-accent-50 text-accent-600 dark:bg-accent-500/15 dark:text-accent-400",
};

const SIZES = { sm: "h-10 w-10 rounded-xl", md: "h-14 w-14 rounded-2xl", lg: "h-24 w-24 rounded-3xl" };

/**
 * Pet photo (served by the API through the BFF) or a species-tinted paw.
 */
export default function PetAvatar({
  photoUrl,
  speciesCode,
  name,
  size = "md",
  className,
}: {
  photoUrl?: string | null;
  speciesCode?: string | null;
  name?: string;
  size?: keyof typeof SIZES;
  className?: string;
}) {
  if (photoUrl) {
    return (
      // Private image behind the BFF (session cookie): next/image can't fetch or optimize it.
      // eslint-disable-next-line @next/next/no-img-element
      <img src={`/api/backend/${photoUrl}`} alt={name ? `Foto de ${name}` : ""} className={cn("shrink-0 object-cover", SIZES[size], className)} />
    );
  }

  return (
    <span
      className={cn(
        "flex shrink-0 items-center justify-center",
        SIZES[size],
        TINT[speciesCode ?? ""] ?? "bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400",
        className,
      )}
    >
      <svg viewBox="0 0 32 32" className="h-[58%] w-[58%]" fill="currentColor" aria-hidden>
        <ellipse cx="8" cy="12.2" rx="2.6" ry="3.2" />
        <ellipse cx="13" cy="7.8" rx="2.6" ry="3.2" />
        <ellipse cx="19" cy="7.8" rx="2.6" ry="3.2" />
        <ellipse cx="24" cy="12.2" rx="2.6" ry="3.2" />
        <path d="M16 14c-4.2 0-8 5-8 8.4 0 2.3 1.7 3.4 3.7 3.4 1.6 0 2.8-.8 4.3-.8s2.7.8 4.3.8c2 0 3.7-1.1 3.7-3.4C24 19 20.2 14 16 14Z" />
      </svg>
    </span>
  );
}
