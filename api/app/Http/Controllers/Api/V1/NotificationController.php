<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The bell: my notifications in this clinic (docs/notificaciones.md). */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $mine = StaffNotification::where('user_id', $request->user()->id);

        return response()->json([
            'data' => (clone $mine)->latest()->orderByDesc('id')->limit(30)->get()->map(fn (StaffNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            'unread' => (clone $mine)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        StaffNotification::where('user_id', $request->user()->id)->whereKey($notification)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->index($request);
    }

    public function readAll(Request $request): JsonResponse
    {
        StaffNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->index($request);
    }
}
