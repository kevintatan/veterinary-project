<?php

namespace App\Auth;

/**
 * Fine-grained permissions. Grows with each phase; roles map to these
 * in ClinicRole::permissions() and are synced by RolesAndPermissionsSeeder.
 */
enum Permission: string
{
    case ClinicSettingsManage = 'clinic.settings.manage';

    case BranchesView = 'branches.view';
    case BranchesManage = 'branches.manage';

    case MembersView = 'members.view';
    case MembersInvite = 'members.invite';
    case MembersManage = 'members.manage';

    case SubscriptionView = 'subscription.view';
    case SubscriptionManage = 'subscription.manage';

    // Tutores y mascotas.
    case PatientsView = 'patients.view';
    case PatientsManage = 'patients.manage';
    case PatientsDelete = 'patients.delete';

    // Agenda.
    case AppointmentsView = 'appointments.view';
    case AppointmentsManage = 'appointments.manage';
    case AgendaSettingsManage = 'agenda.settings.manage'; // services, schedules, blocks

    // Historia clínica.
    case ClinicalRecordsView = 'clinical_records.view';
    case ClinicalRecordsCreate = 'clinical_records.create';
    case ClinicalRecordsEditLocked = 'clinical_records.edit_locked'; // edit others' records and after the edit window
    case ClinicalRecordsVoid = 'clinical_records.void';               // void any record

    // Tareas: every member works with their own; managing everyone's is admin.
    case TasksView = 'tasks.view';
    case TasksManage = 'tasks.manage';
    case TasksManageAll = 'tasks.manage_all';

    // Clinic's own catalog entries (breeds, vaccines, procedures, alerts). Reading is open to members.
    case CatalogsManage = 'catalogs.manage';

    // Inventory (docs/inventario.md): everyone sees stock; reception manages products,
    // purchases and transfers; adjustments and costs are for the admin.
    case InventoryView = 'inventory.view';
    case InventoryManage = 'inventory.manage';
    case InventoryAdjust = 'inventory.adjust';

    // Sales and payments (docs/inventario.md, V2): everyone sees; reception charges; voiding is for the admin.
    case SalesView = 'sales.view';
    case SalesManage = 'sales.manage';
    case SalesVoid = 'sales.void';

    // Sales report and export for the accountant (docs/inventario.md, V4). The daily close only needs sales.manage.
    case ReportsView = 'reports.view';
}
