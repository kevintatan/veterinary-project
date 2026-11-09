import type { DocumentBlock } from "@/lib/documents";

/**
 * A consent as the owner reads it on their phone (remote link, portal):
 * letterhead, key facts and the frozen text. Same content as the PDF.
 */
export default function DocumentPaper({
  clinic,
  title,
  date,
  facts,
  blocks,
}: {
  clinic: { name: string; tax_id?: string | null; address?: string | null };
  title: string;
  date: string;
  facts: [string, string][];
  blocks: DocumentBlock[];
}) {
  return (
    <article className="rounded-2xl border border-gray-200 bg-white px-5 py-6 dark:border-gray-800 dark:bg-gray-900">
      <header className="border-b-2 border-brand-500 pb-3">
        <p className="font-bold text-brand-600 dark:text-brand-400">{clinic.name}</p>
        <p className="text-theme-xs text-gray-500">{[clinic.tax_id && `NIT ${clinic.tax_id}`, clinic.address, date].filter(Boolean).join(" · ")}</p>
      </header>
      <h2 className="mt-5 text-center text-base font-semibold">{title}</h2>
      <dl className="mt-4 grid gap-3 rounded-xl bg-gray-50 p-4 text-theme-sm sm:grid-cols-2 dark:bg-white/3">
        {facts.map(([term, value]) => (
          <div key={term} className="min-w-0">
            <dt className="text-theme-xs text-gray-500">{term}</dt>
            <dd className="font-medium">{value}</dd>
          </div>
        ))}
      </dl>
      <div className="mt-5 space-y-3 text-[15px] leading-relaxed text-gray-800 dark:text-gray-200">
        {blocks.map((b, i) =>
          b.type === "list" ? (
            <ul key={i} className="list-disc space-y-2 pl-5">
              {b.items.map((item, j) => (
                <li key={j}>{item}</li>
              ))}
            </ul>
          ) : (
            <p key={i}>{b.text}</p>
          ),
        )}
        <p>En constancia se firma electrónicamente el {date}.</p>
      </div>
    </article>
  );
}
