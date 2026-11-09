<?php

namespace App\Auth;

/**
 * Global role templates (roles.clinic_id = null), assigned per clinic.
 */
enum ClinicRole: string
{
    case Admin = 'admin';
    case Veterinarian = 'veterinarian';
    case Receptionist = 'receptionist';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Veterinarian => 'Veterinario',
            self::Receptionist => 'Recepción',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $common = [
            Permission::BranchesView,
            Permission::MembersView,
            Permission::SubscriptionView,
            Permission::PatientsView,
            Permission::PatientsManage,
            Permission::AppointmentsView,
            Permission::AppointmentsManage,
            Permission::ClinicalRecordsView,
            Permission::TasksView,
            Permission::TasksManage,
            Permission::InventoryView,
            Permission::SalesView,
        ];

        return match ($this) {
            self::Admin => Permission::cases(),
            // Vets write clinical records; they edit/void only their own, within the edit window.
            self::Veterinarian => [...$common, Permission::ClinicalRecordsCreate],
            self::Receptionist => [...$common, Permission::InventoryManage, Permission::SalesManage],
        };
    }
}
