"use client";

import OwnerPetPicker from "@/components/domain/OwnerPetPicker";
import { Field, Select } from "@/components/form/fields";
import { useSession } from "@/components/providers/SessionProvider";
import Button from "@/components/ui/button/Button";
import { Modal } from "@/components/ui/modal";
import { api } from "@/lib/api";
import type { SaleDetail } from "@/lib/sales";
import { useMutation } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";

/** Start a sale for a tutor (their account) or at the counter, then go to it. */
export default function NewSaleModal({ owner: preset = null, onClose }: { owner?: { id: string; full_name: string } | null; onClose: () => void }) {
  const { branches } = useSession();
  const router = useRouter();
  const [owner, setOwner] = useState<{ id: string; full_name: string } | null>(preset);
  const [counter, setCounter] = useState(false);
  const [branchId, setBranchId] = useState(branches[0]?.id ?? "");

  const create = useMutation({
    mutationFn: () => api<{ data: SaleDetail }>("sales", { method: "POST", body: { branch_id: branchId, owner_id: counter ? null : owner?.id } }).then((r) => r.data),
    onSuccess: (sale) => router.push(`/app/ventas/${sale.id}`),
  });

  return (
    <Modal isOpen onClose={onClose} className="m-4 w-full max-w-md rounded-2xl bg-white p-6 dark:bg-gray-900">
      <h2 className="pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Nueva venta</h2>
      <p className="mt-1 pr-12 text-theme-sm text-gray-500">Para un tutor (queda en su cuenta) o de mostrador.</p>

      <div className="mt-5 space-y-4">
        {!counter && <OwnerPetPicker owner={owner} petId="" onChange={(o) => setOwner(o)} withPet={false} hint="nombre, documento o celular" autoFocus />}
        <label className="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
          <input type="checkbox" checked={counter} onChange={(e) => setCounter(e.target.checked)} className="accent-brand-500" />
          Venta de mostrador (sin tutor)
        </label>
        {branches.length > 1 && (
          <Field label="Sede" htmlFor="sale-branch" hint="De aquí salen los productos.">
            <Select id="sale-branch" value={branchId} onChange={(e) => setBranchId(e.target.value)}>
              {branches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </Select>
          </Field>
        )}
        {create.error && <p className="text-theme-sm text-error-600">{create.error.message}</p>}
      </div>

      <div className="mt-6 flex justify-end gap-3">
        <Button variant="outline" onClick={onClose}>
          Cancelar
        </Button>
        <Button onClick={() => create.mutate()} loading={create.isPending} disabled={!counter && !owner}>
          Crear venta
        </Button>
      </div>
    </Modal>
  );
}
