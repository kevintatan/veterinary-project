<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Models\Clinic;
use App\Models\Task;
use App\Models\User;
use App\Support\ClinicTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    private const RELATIONS = ['assignee', 'creator', 'pet', 'owner'];

    /**
     * scope: mine (assigned to me, default) · created (by me) · all (whole clinic).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'scope' => ['sometimes', Rule::in(['mine', 'created', 'all'])],
            'status' => ['sometimes', Rule::in(array_keys(Task::STATUSES))],
            'pet_id' => ['nullable', 'uuid'],
            'owner_id' => ['nullable', 'uuid'],
        ], [], trans('fields'));

        $user = $request->user();

        $tasks = Task::query()
            ->with(self::RELATIONS)
            ->when(($filters['scope'] ?? 'mine') === 'mine', fn ($q) => $q->where('assignee_id', $user->id))
            ->when(($filters['scope'] ?? null) === 'created', fn ($q) => $q->where('created_by', $user->id))
            ->where('status', $filters['status'] ?? 'pending')
            ->when($filters['pet_id'] ?? null, fn ($q, $id) => $q->where('pet_id', $id))
            ->when($filters['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id))
            ->orderByRaw('due_at IS NULL, due_at')
            ->latest()
            ->paginate(30);

        $clinic = $this->clinic($request);

        return response()->json([
            'data' => $tasks->getCollection()->map(fn (Task $t) => $this->present($t, $clinic, $user)),
            'meta' => ['current_page' => $tasks->currentPage(), 'last_page' => $tasks->lastPage(), 'total' => $tasks->total()],
        ]);
    }

    public function store(TaskRequest $request): JsonResponse
    {
        $data = $request->taskData();
        $task = Task::create([...$data, 'created_by' => $request->user()->id, 'assignee_id' => $data['assignee_id'] ?? $request->user()->id]);

        return response()->json(['data' => $this->present($task->load(self::RELATIONS), $this->clinic($request), $request->user())], Response::HTTP_CREATED);
    }

    public function update(TaskRequest $request, Task $task): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user, $task), Response::HTTP_FORBIDDEN, 'Solo quien creó la tarea, su responsable o un administrador puede modificarla.');

        $data = $request->taskData();
        $task->fill(Arr::except($data, ['status']));

        if (isset($data['status']) && $data['status'] !== $task->status) {
            $task->status = $data['status'];
            $done = $data['status'] === 'done';
            $task->completed_at = $done ? now() : null;
            $task->completed_by = $done ? $user->id : null;
        }

        $task->save();

        return response()->json(['data' => $this->present($task->load(self::RELATIONS), $this->clinic($request), $user)]);
    }

    private function canManage(User $user, Task $task): bool
    {
        return $task->created_by === $user->id
            || $task->assignee_id === $user->id
            || $user->can(Permission::TasksManageAll->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Task $task, Clinic $clinic, User $user): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => ['value' => $task->status, 'label' => Task::STATUSES[$task->status]],
            'is_overdue' => $task->isOverdue(),
            'due_at' => ClinicTime::iso($task->due_at, $clinic),
            'assignee' => $task->assignee?->only(['id', 'name']),
            'created_by' => $task->creator?->only(['id', 'name']),
            'pet' => $task->pet?->only(['id', 'name']),
            'owner' => $task->owner?->only(['id', 'full_name']),
            'source' => $task->source_type === null ? null : ['type' => $task->source_type, 'id' => $task->source_id],
            'completed_at' => ClinicTime::iso($task->completed_at, $clinic),
            'can_edit' => $this->canManage($user, $task),
            'created_at' => ClinicTime::iso($task->created_at, $clinic),
        ];
    }

    private function clinic(Request $request): Clinic
    {
        return $request->attributes->get('clinic');
    }
}
