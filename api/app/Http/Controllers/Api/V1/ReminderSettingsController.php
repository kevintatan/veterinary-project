<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Messaging\Reminders\ReminderPlanner;
use App\Messaging\Reminders\ReminderSettings;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Clinical\ClinicalRecord;
use App\Models\Message;
use App\Models\Owner;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Automatic reminders as the clinic sees them (Fase 4c): its rules and sending
 * window, this month's usage against the plan, and the list of what is
 * scheduled, sent or could not be sent (and why).
 */
class ReminderSettingsController extends Controller
{
    /** Offsets offered in the UI (minutes before). */
    private const OFFSETS = [
        'appointment' => [-120, -720, -1440, -2880],
        'next_control' => [-4320, -10080, -20160],
    ];

    public function show(Request $request): JsonResponse
    {
        $clinic = $this->clinic($request);
        $subscription = Subscription::current();

        return response()->json(['data' => [
            'driver' => config('whatsapp.driver'),
            'plan' => [
                'name' => $subscription?->plan->name,
                'automatic' => (bool) ($subscription?->plan->features['whatsapp_bot'] ?? false),
                'monthly_limit' => $subscription?->plan->limits['whatsapp_messages'] ?? 0,
            ],
            'used_this_month' => UsageCounter::used('whatsapp_utility'),
            'window' => ReminderSettings::for($clinic)->toArray(),
            'rules' => ReminderRule::orderBy('trigger')->get()->map(fn (ReminderRule $r) => [
                'id' => $r->id,
                'trigger' => $r->trigger,
                'record_type' => $r->record_type,
                'offset_minutes' => $r->offset_minutes,
                'is_active' => $r->is_active,
                'offsets' => self::OFFSETS[$r->trigger],
            ]),
            'counts' => Reminder::query()
                ->select('status', DB::raw('count(*) as total'))
                ->where('updated_at', '>=', now()->subDays(30))
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]]);
    }

    public function update(Request $request, ReminderPlanner $planner): JsonResponse
    {
        $data = $request->validate([
            'window' => ['required', 'array'],
            ...ReminderSettings::rules('window.'),
            'rules' => ['required', 'array'],
            'rules.*.id' => ['required', 'uuid', Rule::exists('reminder_rules', 'id')->where('clinic_id', $this->clinic($request)->id)],
            'rules.*.is_active' => ['required', 'boolean'],
            'rules.*.offset_minutes' => ['required', 'integer', Rule::in(array_merge(...array_values(self::OFFSETS)))],
        ], [], ['window.send_from' => 'desde', 'window.send_until' => 'hasta', 'window.min_notice_hours' => 'anticipación mínima']);

        $clinic = $this->clinic($request);
        $clinic->update(['settings' => array_merge($clinic->settings ?? [], ['reminders' => $data['window']])]);

        foreach ($data['rules'] as $rule) {
            ReminderRule::whereKey($rule['id'])->update(['is_active' => $rule['is_active'], 'offset_minutes' => $rule['offset_minutes']]);
        }

        $planner->replanClinic();

        return $this->show($request);
    }

    /**
     * Scheduled, sent, or "could not send" (skipped/failed), newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['view' => ['required', Rule::in(['upcoming', 'sent', 'issues'])]]);
        $clinic = $this->clinic($request);

        $query = Reminder::query()->with(['owner:id,full_name,mobile_phone', 'message:id,body,status,provider,sent_at,delivered_at,read_at', 'remindable']);

        match ($filters['view']) {
            'upcoming' => $query->whereIn('status', [Reminder::PENDING, Reminder::QUEUED, Reminder::SENDING])->orderBy('scheduled_for'),
            'sent' => $query->where('status', Reminder::SENT)->orderByDesc('updated_at'),
            'issues' => $query->where(fn ($q) => $q->where('status', Reminder::FAILED)->orWhere('status', 'like', 'skipped_%'))->orderByDesc('updated_at'),
        };

        $page = $query->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Reminder $r) => $this->reminderData($r, $clinic)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Messages log of one owner (any channel, manual or automatic). */
    public function ownerMessages(Request $request, Owner $owner): JsonResponse
    {
        $clinic = $this->clinic($request);

        return response()->json(['data' => Message::with('sender:id,name')
            ->where('owner_id', $owner->id)
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'channel' => $m->channel,
                'provider' => $m->provider,
                'purpose' => $m->purpose,
                'body' => $m->body,
                // Template quick replies (payload strings); in simulated mode staff can tap them as the owner.
                'buttons' => $m->direction === 'outbound' ? array_values(array_filter($m->payload['buttons'] ?? [], 'is_string')) : [],
                'status' => $m->status,
                'sent_by' => $m->sender?->name,
                'sent_at' => ClinicTime::iso($m->sent_at ?? $m->created_at, $clinic),
                'delivered_at' => ClinicTime::iso($m->delivered_at, $clinic),
                'read_at' => ClinicTime::iso($m->read_at, $clinic),
            ]), 'meta' => ['simulated' => config('whatsapp.driver') === 'fake']]);
    }

    /** @return array<string, mixed> */
    private function reminderData(Reminder $r, Clinic $clinic): array
    {
        $subject = $r->remindable;
        $pet = $subject instanceof Appointment ? $subject->pet : ($subject instanceof ClinicalRecord ? $subject->pet : null);

        return [
            'id' => $r->id,
            'purpose' => $r->purpose,
            'status' => ['value' => $r->status, 'label' => $r->statusLabel()],
            'scheduled_for' => ClinicTime::iso($r->scheduled_for, $clinic),
            'event_at' => ClinicTime::iso($r->event_at, $clinic),
            'attempts' => $r->attempts,
            'last_error' => $r->last_error,
            'pet' => $pet === null ? null : ['id' => $pet->id, 'name' => $pet->name],
            'owner' => $r->owner === null ? null : ['id' => $r->owner->id, 'full_name' => $r->owner->full_name],
            'message' => $r->message === null ? null : [
                'body' => $r->message->body,
                'status' => $r->message->status,
                'provider' => $r->message->provider,
                'sent_at' => ClinicTime::iso($r->message->sent_at, $clinic),
            ],
        ];
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
