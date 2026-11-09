"use client";

import Button from "@/components/ui/button/Button";
import { Modal } from "@/components/ui/modal";
import { ApiError, api } from "@/lib/api";
import { inventoryKeys } from "@/lib/inventory";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

type Result = { created: number; updated: number; errors: { line: number; name: string | null; message: string }[] };

/**
 * Bring the clinic's product list from Excel (saved as CSV). Existing products
 * (same code or name) get their prices updated; new ones are created with their
 * initial stock in the branch shown.
 */
export default function ImportModal({ branchId, branchName, onClose }: { branchId: string; branchName: string; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [file, setFile] = useState<File | null>(null);

  const upload = useMutation({
    mutationFn: () => {
      const form = new FormData();
      form.append("file", file!);
      form.append("branch_id", branchId);
      return api<{ data: Result }>("inventory/import", { method: "POST", body: form }).then((r) => r.data);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: inventoryKeys.all }),
  });
  const result = upload.data;
  const fileError = upload.error instanceof ApiError ? (upload.error.errors.file?.[0] ?? upload.error.message) : null;

  return (
    <Modal isOpen onClose={onClose} className="m-4 w-full max-w-lg rounded-2xl bg-white p-6 dark:bg-gray-900">
      <h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Importar productos</h2>
      <p className="mt-1 pr-12 text-theme-sm text-gray-500">Desde una hoja de Excel. Las existencias iniciales quedan en {branchName}.</p>

      {!result ? (
        <div className="mt-5 space-y-4">
          <ol className="space-y-2 text-theme-sm text-gray-700 dark:text-gray-300">
            <li>
              <b>1.</b>{" "}
              <a href="/api/backend/inventory/import-template" download="plantilla-productos.csv" className="font-medium text-brand-600 underline">
                Descarga la plantilla
              </a>{" "}
              y ábrela en Excel. Columnas: nombre, código, tipo, unidad, precio de venta, costo, IVA, existencia mínima y existencia inicial. Solo el nombre es obligatorio.
            </li>
            <li>
              <b>2.</b> Llena tus productos (un producto por fila) y guárdala con <b>Archivo → Guardar como → CSV</b>.
            </li>
            <li>
              <b>3.</b> Súbela aquí. Si un producto ya existe (mismo código o nombre), se actualizan sus precios; las existencias de los que ya tenías no cambian.
            </li>
          </ol>
          <label className="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-dashed border-gray-300 px-4 py-6 text-center hover:border-brand-300 dark:border-gray-700">
            <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">{file ? file.name : "Elegir archivo CSV"}</span>
            <span className="text-theme-xs text-gray-500">Hasta 2 MB · 2.000 productos</span>
            <input type="file" accept=".csv,text/csv" className="sr-only" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
          </label>
          {fileError && <p className="text-theme-sm text-error-600">{fileError}</p>}
        </div>
      ) : (
        <div className="mt-5 space-y-4">
          <div className="grid grid-cols-3 gap-3 text-center">
            <Count label="Creados" value={result.created} tone="text-success-600" />
            <Count label="Actualizados" value={result.updated} tone="text-brand-600" />
            <Count label="Con error" value={result.errors.length} tone={result.errors.length ? "text-error-600" : "text-gray-400"} />
          </div>
          {result.errors.length > 0 && (
            <div className="max-h-56 overflow-y-auto rounded-xl border border-error-200 dark:border-error-500/30">
              <p className="sticky top-0 bg-error-50 px-4 py-2 text-theme-xs font-semibold text-error-700 dark:bg-error-500/10">Estas filas no se importaron. Corrígelas en el archivo y vuelve a subirlo: las demás no se duplican.</p>
              <ul className="divide-y divide-gray-100 text-theme-sm dark:divide-gray-800">
                {result.errors.map((e) => (
                  <li key={e.line} className="px-4 py-2">
                    <b>Fila {e.line}</b>
                    {e.name ? ` · ${e.name}` : ""}: <span className="text-gray-600 dark:text-gray-300">{e.message}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      <div className="mt-6 flex justify-end gap-3">
        <Button variant="outline" onClick={onClose}>
          {result ? "Cerrar" : "Cancelar"}
        </Button>
        {!result && (
          <Button onClick={() => upload.mutate()} loading={upload.isPending} disabled={!file}>
            Importar
          </Button>
        )}
      </div>
    </Modal>
  );
}

function Count({ label, value, tone }: { label: string; value: number; tone: string }) {
  return (
    <div className="rounded-xl border border-gray-200 py-3 dark:border-gray-800">
      <p className={`text-2xl font-semibold ${tone}`}>{value}</p>
      <p className="text-theme-xs text-gray-500">{label}</p>
    </div>
  );
}
