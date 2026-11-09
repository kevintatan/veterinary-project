<?php

namespace App\Sales;

use App\Enums\AppointmentStatus;
use App\Inventory\Inventory;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Inventory\Product;
use App\Models\Owner;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Charges that come from the care itself (docs/inventario.md, V3):
 * "Cobrar" an appointment, and the product used in a vaccine, deworming or
 * procedure. Everything lands in the owner's account of the day, so a visit
 * is one charge.
 */
final class CareCharges
{
    public function __construct(private readonly Sales $sales, private readonly Inventory $inventory) {}

    /**
     * The account where this appointment is charged: if it was already charged,
     * that sale; otherwise the owner's account of the day with the appointment's
     * service added.
     */
    public function chargeAppointment(Appointment $appointment, User $actor, Clinic $clinic): Sale
    {
        if ($appointment->owner_id === null) {
            throw ValidationException::withMessages(['appointment' => 'La cita no tiene tutor: crea una venta de mostrador desde Ventas.']);
        }
        if (in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow, AppointmentStatus::Requested], true)) {
            throw ValidationException::withMessages(['appointment' => 'Esta cita no se puede cobrar en su estado actual.']);
        }

        return DB::transaction(function () use ($appointment, $actor, $clinic) {
            $existing = SaleItem::where('appointment_id', $appointment->id)
                ->whereHas('sale', fn ($q) => $q->where('status', '<>', Sale::VOID))
                ->first();
            if ($existing !== null) {
                return $existing->sale;
            }

            $sale = $this->sales->accountFor(Owner::findOrFail($appointment->owner_id), Branch::findOrFail($appointment->branch_id), $actor, $clinic->timezone);

            if ($appointment->service_id !== null) {
                $this->sales->addItem($sale, ['type' => 'service', 'service_id' => $appointment->service_id, 'quantity' => 1, 'appointment_id' => $appointment->id], $actor);
            }

            return $sale->refresh();
        });
    }

    /**
     * A product used in a clinical record. charge=true → it goes to the owner's
     * account (stock moves as a sale); charge=false → included, only out of stock.
     */
    public function useProduct(ClinicalRecord $record, string $productId, float $quantity, bool $charge, User $actor, Clinic $clinic): void
    {
        $product = Product::where('is_active', true)->findOrFail($productId);
        $branch = Branch::findOrFail($record->branch_id);

        if ($charge) {
            $owner = Owner::findOrFail($record->pet()->value('owner_id'));
            $sale = $this->sales->accountFor($owner, $branch, $actor, $clinic->timezone);
            $this->sales->addItem($sale, ['type' => 'product', 'product_id' => $product->id, 'quantity' => $quantity, 'clinical_record_id' => $record->id], $actor);

            return;
        }

        if ($product->tracks_stock) {
            $this->inventory->record($product, $branch, 'clinical_use', (string) -$quantity, $actor, [
                'reference_type' => 'clinical_record',
                'reference_id' => $record->id,
                'note' => 'Uso en atención (incluido, sin cobro)',
            ]);
        }
    }
}
