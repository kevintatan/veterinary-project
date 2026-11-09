<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Messaging\Bot\BotActions;
use App\Messaging\Bot\WebhookProcessor;
use App\Messaging\Bot\WhatsAppReplier;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Message;
use App\Models\Owner;
use App\Models\Subscription;
use App\Models\WhatsAppConversation;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The clinic's WhatsApp inbox (Fase 4d): conversations the bot handed over,
 * the thread and staff replies. Conversations are a platform table, so every
 * query here filters by active_clinic_id explicitly.
 */
class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['filter' => ['nullable', Rule::in(['attention', 'all'])]]);
        $clinic = $this->clinic($request);

        // The chat with the team is a higher-plan feature: without it the menu hides the inbox.
        if (! Subscription::allows('whatsapp_inbox')) {
            return response()->json(['data' => [], 'meta' => ['enabled' => false, 'attention' => 0, 'simulated' => config('whatsapp.driver') === 'fake']]);
        }

        $conversations = WhatsAppConversation::where('active_clinic_id', $clinic->id)
            ->when(($data['filter'] ?? 'attention') === 'attention', fn ($q) => $q->whereNotNull('needs_attention_since'))
            ->orderByRaw('needs_attention_since IS NULL')
            ->orderByDesc('last_inbound_at')
            ->limit(50)
            ->get();

        $owners = Owner::whereIn('id', $conversations->pluck('owner_id')->filter())->get(['id', 'full_name'])->keyBy('id');

        return response()->json(['data' => $conversations->map(function (WhatsAppConversation $c) use ($owners, $clinic) {
            $last = Message::where(fn ($q) => $q->where('from_phone', $c->phone)->orWhere('to_phone', $c->phone))->latest('created_at')->latest('id')->first();

            return [
                'id' => $c->id,
                'phone' => $c->phone,
                'owner' => ($o = $owners[$c->owner_id] ?? null) ? ['id' => $o->id, 'full_name' => $o->full_name] : null,
                'needs_attention_since' => ClinicTime::iso($c->needs_attention_since, $clinic),
                'window_open' => $c->windowOpen(),
                'last_message' => $last ? ['body' => $last->body, 'direction' => $last->direction, 'at' => ClinicTime::iso($last->created_at, $clinic)] : null,
            ];
        }), 'meta' => [
            'enabled' => true,
            'attention' => WhatsAppConversation::where('active_clinic_id', $clinic->id)->whereNotNull('needs_attention_since')->count(),
            'simulated' => config('whatsapp.driver') === 'fake', // the UI shows the owner simulator
        ]]);
    }

    public function show(Request $request, string $conversation): JsonResponse
    {
        $clinic = $this->clinic($request);
        $c = $this->find($clinic, $conversation);

        $messages = Message::with('sender:id,name')
            ->where(fn ($q) => $q->where('from_phone', $c->phone)->orWhere('to_phone', $c->phone))
            // created_at has 1 s precision: the id (UUIDv7, time ordered to the ms) breaks ties.
            ->latest('created_at')->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return response()->json(['data' => [
            'id' => $c->id,
            'phone' => $c->phone,
            'owner' => $c->owner_id ? Owner::find($c->owner_id, ['id', 'full_name']) : null,
            'needs_attention_since' => ClinicTime::iso($c->needs_attention_since, $clinic),
            'window_open' => $c->windowOpen(),
            'window_closes_at' => ClinicTime::iso($c->last_inbound_at?->copy()->addDay(), $clinic),
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'provider' => $m->provider,
                'purpose' => $m->purpose,
                'body' => $m->body,
                'buttons' => $m->direction === 'outbound' ? collect($m->payload['buttons'] ?? [])->map(fn ($b) => is_array($b) ? ['id' => $b[0], 'title' => $b[1]] : ['id' => $b, 'title' => null])->values() : [],
                'status' => $m->status,
                'sent_by' => $m->sender?->name,
                'at' => ClinicTime::iso($m->created_at, $clinic),
            ]),
        ]]);
    }

    /** Staff reply: free-form, only inside Meta's 24 h window. */
    public function reply(Request $request, string $conversation, WhatsAppReplier $replier): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']], [], ['text' => 'mensaje']);
        $clinic = $this->clinic($request);
        $c = $this->find($clinic, $conversation);

        if (! $c->windowOpen()) {
            throw ValidationException::withMessages(['text' => 'Pasaron más de 24 horas desde el último mensaje del tutor: WhatsApp solo permite escribirle con plantillas aprobadas. Llámalo o espera a que vuelva a escribir.']);
        }

        $message = $replier->reply($clinic->id, $c->phone, $c->owner_id, $data['text'], sentBy: $request->user()->id, purpose: 'staff_reply');
        abort_if($message === null, 502, 'WhatsApp no aceptó el mensaje. Intenta de nuevo en un momento.');

        $c->update(['needs_attention_since' => null, 'state' => WhatsAppConversation::IDLE]);

        return $this->show($request, $c->id);
    }

    public function resolve(Request $request, string $conversation): JsonResponse
    {
        $this->find($this->clinic($request), $conversation)->update(['needs_attention_since' => null, 'state' => WhatsAppConversation::IDLE]);

        return response()->json(['ok' => true]);
    }

    /**
     * Development only (WHATSAPP_DRIVER=fake): act as the owner. Builds a
     * Meta-format webhook payload and runs it through the same processor.
     */
    public function simulate(Request $request, WebhookProcessor $processor): JsonResponse
    {
        abort_unless(config('whatsapp.driver') === 'fake', 404);

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+\d{8,15}$/'],
            'payload' => ['nullable', 'string', 'max:300'],
            'title' => ['nullable', 'string', 'max:60'],
            'text' => ['required_without:payload', 'nullable', 'string', 'max:1000'],
        ]);

        // Only phones of this clinic's owners or online requests (the simulator must not reach other clinics).
        abort_unless(
            Owner::where('mobile_phone', $data['phone'])->exists() || Appointment::where('booking_details->phone', $data['phone'])->exists(),
            404,
        );

        $message = ['from' => ltrim($data['phone'], '+'), 'id' => 'sim.'.Str::uuid7(), 'timestamp' => (string) now()->timestamp];
        // Same shapes Meta sends: template quick replies arrive as "button", bot buttons as "interactive".
        $action = BotActions::parse($data['payload'] ?? null)[0] ?? null;
        $message += match (true) {
            ! isset($data['payload']) => ['type' => 'text', 'text' => ['body' => $data['text']]],
            in_array($action, BotActions::REMINDER_BUTTONS, true) => ['type' => 'button', 'button' => ['payload' => $data['payload'], 'text' => $data['title'] ?? $action]],
            default => ['type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => $data['payload'], 'title' => $data['title'] ?? $action]]],
        };

        $processor->process(['entry' => [['changes' => [['value' => ['messages' => [$message], 'contacts' => [['wa_id' => $message['from'], 'profile' => ['name' => 'Simulado']]]]]]]]]);

        return response()->json(['ok' => true]);
    }

    private function find(Clinic $clinic, string $id): WhatsAppConversation
    {
        abort_unless(Subscription::allows('whatsapp_inbox'), 403, 'Tu plan no incluye la bandeja de WhatsApp.');

        return WhatsAppConversation::where('active_clinic_id', $clinic->id)->findOrFail($id);
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
