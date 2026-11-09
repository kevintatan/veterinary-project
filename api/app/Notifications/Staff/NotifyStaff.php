<?php

namespace App\Notifications\Staff;

use App\Auth\Permission;
use App\Models\ClinicMembership;
use App\Models\StaffNotification;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Puts a notification in the bell of the people who should act on it
 * (docs/notificaciones.md). Runs inside the clinic's tenant context.
 *
 *   app(NotifyStaff::class)->withPermission(Permission::AppointmentsManage, 'booking_request', 'Nueva solicitud…', '…', '/app/agenda?…');
 *   app(NotifyStaff::class)->user($userId, 'task_assigned', …);
 */
final class NotifyStaff
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** Everyone active in the clinic whose role has the permission (except who caused it). */
    public function withPermission(Permission $permission, string $type, string $title, ?string $body = null, ?string $url = null, ?string $exceptUserId = null): int
    {
        $userIds = ClinicMembership::query()
            ->where('status', ClinicMembership::STATUS_ACTIVE)
            ->pluck('user_id')
            ->reject(fn (string $id) => $id === $exceptUserId);

        $sent = 0;

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            // Fresh roles: permissions are per clinic (team = tenant).
            $user->unsetRelation('roles')->unsetRelation('permissions');

            if ($user->hasPermissionTo($permission->value)) {
                $this->create($user->id, $type, $title, $body, $url);
                $sent++;
            }
        }

        return $sent;
    }

    public function user(string $userId, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        $this->create($userId, $type, $title, $body, $url);
    }

    private function create(string $userId, string $type, string $title, ?string $body, ?string $url): void
    {
        StaffNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => mb_substr($title, 0, 150),
            'body' => $body === null ? null : mb_substr($body, 0, 300),
            'url' => $url,
        ]);
    }
}
