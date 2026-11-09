import { Logo } from "@/components/brand/Logo";
import Link from "next/link";

/** Public legal pages (terms, data policy): readable column, no app chrome. */
export default function LegalLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-gray-50 px-4 py-10 text-gray-800 dark:bg-gray-950 dark:text-gray-200">
      <div className="mx-auto max-w-2xl">
        <Link href="/" aria-label="Zooetia">
          <Logo className="mb-8 h-8 w-auto" />
        </Link>
        <article className="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 text-[15px] leading-relaxed sm:p-10 dark:border-gray-800 dark:bg-gray-900 [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_li]:ml-5 [&_li]:list-disc">
          {children}
        </article>
      </div>
    </div>
  );
}
