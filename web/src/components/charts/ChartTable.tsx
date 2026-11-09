/**
 * Table view of a chart: every value reachable without hovering
 * (accessibility + contrast relief for the coral series).
 */
export default function ChartTable({
  caption,
  columns,
  rows,
}: {
  caption: string;
  columns: string[];
  rows: (string | number)[][];
}) {
  return (
    <div className="max-h-72 overflow-auto px-5 pb-5">
      <table className="w-full text-left text-theme-sm">
        <caption className="sr-only">{caption}</caption>
        <thead className="sticky top-0 bg-white dark:bg-gray-900">
          <tr>
            {columns.map((column, i) => (
              <th key={column} scope="col" className={`py-2 font-medium text-gray-500 ${i > 0 ? "text-right" : ""}`}>
                {column}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
          {rows.map((row) => (
            <tr key={String(row[0])}>
              {row.map((cell, i) => (
                <td key={i} className={`py-1.5 text-gray-700 dark:text-gray-300 ${i > 0 ? "text-right tabular" : ""}`}>
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
