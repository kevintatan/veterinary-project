<?php

use Illuminate\Support\Facades\DB;

/*
 * docs/database.md D2: every business table carries clinic_id.
 * Adding a table without it makes this test fail on purpose: either add
 * clinic_id or, if it truly is a platform table, whitelist it here.
 */

const PLATFORM_TABLES = [
    // Laravel / framework
    'migrations', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
    'jobs', 'job_batches', 'failed_jobs',
    // Platform-level (shared by every clinic)
    'users', 'plans', 'clinics', 'permissions', 'role_has_permissions',
    'tutor_accounts', 'tutor_login_codes', // owner portal login: one per person across clinics (links carry clinic_id)
    'whatsapp_conversations', // per phone (shared number); active_clinic_id + explicit scoping in ConversationController
];

/** Tables whose clinic_id may be NULL, and why. */
const NULLABLE_CLINIC_TABLES = [
    'roles',                  // null = global role template
    'activity_log',           // platform actions (e.g. clinic registration)
    'personal_access_tokens', // short-lived "choose a clinic" token
    'messages',               // inbound WhatsApp not yet routed to a clinic (shared number)
    // Mixed catalogs (D9): null = global entry seeded by Zooetia
    'species', 'breeds', 'vaccines', 'procedure_types', 'clinical_alerts',
];

function publicTables(): array
{
    return collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"))
        ->pluck('table_name')
        ->all();
}

function clinicIdColumn(string $table): ?object
{
    return DB::selectOne(
        "SELECT is_nullable, data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = 'clinic_id'",
        [$table],
    );
}

it('has clinic_id on every business table', function () {
    $missing = collect(publicTables())
        ->diff(PLATFORM_TABLES)
        ->filter(fn (string $table) => clinicIdColumn($table) === null)
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('makes clinic_id a NOT NULL uuid unless explicitly allowed', function () {
    $violations = collect(publicTables())
        ->diff(PLATFORM_TABLES)
        ->mapWithKeys(fn (string $table) => [$table => clinicIdColumn($table)])
        ->filter(fn (?object $column, string $table) => $column !== null && (
            $column->data_type !== 'uuid'
            || ($column->is_nullable === 'YES' && ! in_array($table, NULLABLE_CLINIC_TABLES, true))
        ))
        ->keys()
        ->all();

    expect($violations)->toBe([]);
});
