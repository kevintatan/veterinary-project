<?php

namespace App\Messaging\Bot;

use App\Models\Clinic;
use App\Models\Message;
use App\Models\Owner;
use App\Models\WhatsAppConversation;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Processes Meta's webhook payload (Fase 4d): delivery statuses of what we
 * sent, and the owners' messages, routed to the right clinic (shared number,
 * docs/database.md §10):
 *
 *  1. button payload → our message id → its clinic and appointment;
 *  2. a quoted message (context.id) → that message's clinic;
 *  3. the last message we sent to that phone (7 days);
 *  4. a clinic that has an owner with that phone.
 *
 * Platform-level code: it only reads clinic ids with narrow queries, then
 * works inside that clinic like any request (TenantContext::runAs).
 */
class WebhookProcessor
{
    public function __construct(private readonly TenantContext $tenant, private readonly WhatsAppBot $bot) {}

    /** @param array<string, mixed> $payload */
    public function process(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->status($status);
                }

                $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [$c['wa_id'] ?? '' => $c['profile']['name'] ?? null]);
                foreach ($value['messages'] ?? [] as $message) {
                    $this->inbound(InboundMessage::fromMeta($message, $names[$message['from'] ?? ''] ?? null));
                }
            }
        }
    }

    /** @param array<string, mixed> $status */
    private function status(array $status): void
    {
        $row = DB::table('messages')->where('provider_message_id', $status['id'] ?? '')->first(['id', 'clinic_id']);

        if ($row === null || $row->clinic_id === null) {
            return;
        }

        $this->tenant->runAs($row->clinic_id, function () use ($row, $status) {
            $message = Message::find($row->id);
            $at = isset($status['timestamp']) ? now()->setTimestamp((int) $status['timestamp']) : now();

            match ($status['status'] ?? null) {
                'delivered' => $message->update(['status' => $message->read_at ? 'read' : 'delivered', 'delivered_at' => $message->delivered_at ?? $at]),
                'read' => $message->update(['status' => 'read', 'read_at' => $at, 'delivered_at' => $message->delivered_at ?? $at]),
                'failed' => $message->update(['status' => 'failed', 'error' => $status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'failed']),
                default => null,
            };

            if (isset($status['pricing']['category'])) {
                $message->update(['pricing_category' => $status['pricing']['category']]);
            }
        });
    }

    private function inbound(InboundMessage $in): void
    {
        // Meta retries webhooks: the same message must be processed once.
        if ($in->wamid === '' || DB::table('messages')->where('provider_message_id', $in->wamid)->exists()) {
            return;
        }

        $clinicId = $this->route($in);

        if ($clinicId === null) {
            // Nobody to give it to: kept for review (clinic_id null), never lost.
            DB::table('messages')->insert([
                'id' => (string) Str::uuid7(), 'clinic_id' => null, 'channel' => 'whatsapp', 'direction' => 'inbound', 'provider' => config('whatsapp.driver'),
                'from_phone' => $in->from, 'body' => $in->text, 'payload' => json_encode($in->raw), 'provider_message_id' => $in->wamid,
                'status' => 'received', 'created_at' => now(), 'updated_at' => now(),
            ]);
            Log::notice('whatsapp.unrouted', ['from' => $in->from]);

            return;
        }

        $clinic = Clinic::find($clinicId);

        $this->tenant->runAs($clinicId, function () use ($clinic, $in) {
            $related = ($parsed = BotActions::parse($in->payload)) ? Message::find($parsed[1]) : null;
            $owner = ($related?->owner_id ? Owner::find($related->owner_id) : null) ?? Owner::where('mobile_phone', $in->from)->latest('updated_at')->first();

            Message::create([
                'owner_id' => $owner?->id,
                'channel' => 'whatsapp',
                'direction' => 'inbound',
                'provider' => config('whatsapp.driver'),
                'purpose' => $in->type === 'button' ? 'button_reply' : 'owner_message',
                'from_phone' => $in->from,
                'body' => $in->text,
                'payload' => $in->raw,
                'provider_message_id' => $in->wamid,
                'status' => 'received',
                'related_type' => $related?->related_type,
                'related_id' => $related?->related_id,
            ]);

            $conversation = WhatsAppConversation::updateOrCreate(['phone' => $in->from], [
                'active_clinic_id' => $clinic->id,
                'owner_id' => $owner?->id,
                'last_inbound_at' => now(),
            ]);

            $this->bot->handle($clinic, $conversation, $in, $owner);
        });
    }

    private function route(InboundMessage $in): ?string
    {
        if (($parsed = BotActions::parse($in->payload)) !== null) {
            $clinic = DB::table('messages')->where('id', $parsed[1])->value('clinic_id');
            if ($clinic) {
                return $clinic;
            }
        }

        if ($in->contextWamid !== null) {
            $clinic = DB::table('messages')->where('provider_message_id', $in->contextWamid)->value('clinic_id');
            if ($clinic) {
                return $clinic;
            }
        }

        $clinic = DB::table('messages')
            ->where('direction', 'outbound')
            ->where('to_phone', $in->from)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('clinic_id')
            ->latest('created_at')
            ->value('clinic_id');

        return $clinic ?: DB::table('owners')->where('mobile_phone', $in->from)->whereNull('deleted_at')->orderByDesc('updated_at')->value('clinic_id');
    }
}
