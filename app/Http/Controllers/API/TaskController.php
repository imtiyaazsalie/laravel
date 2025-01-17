<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\CreateTaskRequest;
use App\Http\Requests\Tasks\DeleteTaskRequest;
use App\Http\Requests\Tasks\ListTasksRequest;
use App\Http\Requests\Tasks\ReadTaskRequest;
use App\Http\Requests\Tasks\ToggleTaskCompleteRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use App\Services\CrmService;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskController extends Controller
{
    public function __construct(
        protected CrmService $settings
    ) {
        //
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[is_completed]', 'boolean', required: false)]
    #[QueryParam('filter[is_scheduled]', 'boolean', required: false)]
    #[QueryParam('filter[assigned_user_id]', 'integer', required: false)]
    public function list(ListTasksRequest $request)
    {
        $tasks = QueryBuilder::for(Task::class)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('location_id', 'box_facility_id'),
                AllowedFilter::exact('assigned_user_id', 'assignees.user_id'),
                AllowedFilter::exact('is_completed'),
                AllowedFilter::scope('is_scheduled'),
            ])
            ->latest(Task::UPDATED_AT)
            ->with('location', 'createdBy', 'updatedBy', 'assignees')
            ->_paginate();

        return TaskResource::collection($tasks);
    }

    public function show(ReadTaskRequest $request, Task $task)
    {
        return new TaskResource($task->loadMissing('assignees', 'createdBy', 'updatedBy'));
    }

    public function toggleComplete(ToggleTaskCompleteRequest $request, Task $task): TaskResource
    {
        $task->update(['is_completed' => ! $task->is_completed]);

        return new TaskResource($task);
    }

    public function delete(DeleteTaskRequest $request, Task $task)
    {
        DB::transaction(function () use ($task) {
            TaskAssignee::query()
                ->where('task_id', $task->getKey())
                ->delete();

            $task->delete();
        });

        return response()->noContent();
    }

    public function store(CreateTaskRequest $request)
    {
        $task = Task::create([
            ...$request->safe()->only([
                'tenant_id',
                'location_id',
                'summary',
                'description',
                'due_date',
                'is_completed',
            ]),
            'created_by_id' => auth()->user()->getAuthIdentifier(),
            'updated_by_id' => auth()->user()->getAuthIdentifier(),
        ]);

        $task->load('tenant', 'location');

        if ($request->has('assigned_user_ids') && is_array($request->get('assigned_user_ids'))) {

            $users = User::query()
                ->whereKey($request->assigned_user_ids)
                ->whereHas('tenantUser', function ($query) use ($request) {
                    $query->active()->where('box_id', $request->tenant_id);
                })
                ->get();

            TaskAssignee::query()->insert(array_map(
                fn ($id) => [
                    'task_id' => $task->getKey(),
                    'user_id' => $id,
                ],
                $users->modelKeys()
            ));

            $users->each(function ($staff) use ($task) {

                $this->settings->createScheduledEmailForNotification(
                    tenantOrLocation: $task->location ?: $task->tenant,
                    context: 'new_task',
                    recipient: $staff,
                    data: $this->generatePlaceholdersArray($task, $staff)
                );

            });

        }

        return new TaskResource($task->loadMissing('location', 'assignees', 'createdBy', 'updatedBy'));
    }

    private function generatePlaceholdersArray(Task $task, User $coach): array
    {
        return [
            'coach_name' => $coach->name,
            'coach_surname' => $coach->surname,
            'task_name' => $task->summary,
            'task_time' => $task->due_due ? $task->due_date->format('H:i') : 'n/a',
            'task_date' => $task->due_date ? $task->due_date->format('Y-m-d') : 'n/a',
            'task_description' => $task->description,
        ];
    }

    public function update(Task $task, UpdateTaskRequest $request)
    {
        $task->loadMissing('assignees');

        if ($request->has('assigned_user_ids') && is_array($request->get('assigned_user_ids'))) {
            $remove = $task->assignees->filter(fn ($assignee) => ! in_array($assignee->user_id, $request->get('assigned_user_ids')));

            $add = User::query()
                ->whereKey(array_filter($request->assigned_user_ids, fn ($id) => ! $task->assignees->pluck('user_id')->contains($id)))
                ->whereHas('tenantUser', function ($query) use ($task) {
                    $query->active()->where('box_id', $task->tenant_id);
                })
                ->get();

        } else {
            $remove = collect();
            $add = collect();
        }

        DB::transaction(function () use ($task, $request, $remove, $add) {

            $task->update([
                ...$request->only(
                    [
                        'summary',
                        'description',
                        'due_date',
                        'is_completed',
                        'location_id',
                    ]
                ),
                'updated_by_id' => auth()->user()->getAuthIdentifier(),
            ]);

            if ($request->has('assigned_user_ids') && is_array($request->get('assigned_user_ids'))) {
                //remove
                if ($remove->isNotEmpty()) {
                    TaskAssignee::query()
                        ->where('task_id', $task->getKey())
                        ->whereIn('user_id', $remove->modelKeys())
                        ->delete();

                    $remove->each(function ($coach) use ($task) {

                        $this->settings->createScheduledEmailForNotification(
                            tenantOrLocation: $task->tenant,
                            context: 'task_removal',
                            recipient: $coach,
                            data: $this->generatePlaceholdersArray($task, $coach)
                        );

                    });

                }

                // add
                if ($add->isNotEmpty()) {
                    TaskAssignee::query()->insert($add->map(fn ($user) => [
                        'task_id' => $task->getKey(),
                        'user_id' => $user->getKey(),
                    ])->toArray());

                    $add->each(function ($coach) use ($task) {

                        $this->settings->createScheduledEmailForNotification(
                            tenantOrLocation: $task->tenant,
                            context: 'new_task',
                            recipient: $coach,
                            data: $this->generatePlaceholdersArray($task, $coach)
                        );

                    });

                }
            }

        }, 2);

        return new TaskResource($task->load('createdBy', 'updatedBy', 'assignees'));
    }
}
