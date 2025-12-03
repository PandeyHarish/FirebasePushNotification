<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    protected ?FcmService $fcmService = null;

    public function __construct()
    {
        // FcmService will be loaded lazily when needed
    }

    /**
     * Get FCM Service instance (lazy loading)
     */
    protected function getFcmService(): ?FcmService
    {
        if ($this->fcmService === null) {
            try {
                $this->fcmService = app(FcmService::class);
            } catch (\Exception $e) {
                Log::warning('FCM Service not available: ' . $e->getMessage());
                return null;
            }
        }
        return $this->fcmService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Show all tasks, or filter by assigned user if needed
        $tasks = Task::with(['assignedUser', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('tasks.index', compact('tasks'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::select('id', 'name', 'email', 'fcm_token')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $user->has_notifications = !empty($user->fcm_token);
                return $user;
            });
        return view('tasks.create', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date|after_or_equal:today',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = $validated['status'] ?? 'pending';

        $task = Task::create($validated);

        // Send notification if task is assigned to a user
        $notificationSent = false;
        $notificationMessage = '';
        
        if ($task->assigned_to && $task->assignedUser) {
            $fcmService = $this->getFcmService();
            if ($fcmService) {
                try {
                    $user = $task->assignedUser;
                    if ($user->fcm_token) {
                        $this->sendTaskAssignmentNotification($task, $fcmService);
                        $notificationSent = true;
                        $notificationMessage = "Task created and notification sent to {$user->name}!";
                    } else {
                        $notificationMessage = "Task created! Note: {$user->name} hasn't enabled notifications yet. They need to log in to receive notifications.";
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send notification on task creation', [
                        'task_id' => $task->id,
                        'error' => $e->getMessage()
                    ]);
                    $notificationMessage = "Task created! Notification could not be sent.";
                }
            } else {
                $notificationMessage = "Task created! (FCM service not available)";
            }
        } else {
            $notificationMessage = "Task created successfully!";
        }

        return redirect()->route('tasks.index')
            ->with('success', $notificationMessage)
            ->with('notification_sent', $notificationSent);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        $task->load(['assignedUser', 'creator']);
        return view('tasks.show', compact('task'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        $users = User::select('id', 'name', 'email', 'fcm_token')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $user->has_notifications = !empty($user->fcm_token);
                return $user;
            });
        return view('tasks.edit', compact('task', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $oldAssignedTo = $task->assigned_to;
        $task->update($validated);
        
        // Refresh task to get updated relationships
        $task->refresh();

        $notificationSent = false;
        $notificationMessage = 'Task updated successfully!';

        // Send notification if task assignment changed or task was just assigned
        $fcmService = $this->getFcmService();
        if ($fcmService && $task->assigned_to && 
            ($task->assigned_to !== $oldAssignedTo || $oldAssignedTo === null)) {
            try {
                $user = $task->assignedUser;
                if ($user && $user->fcm_token) {
                    $this->sendTaskAssignmentNotification($task, $fcmService);
                    $notificationSent = true;
                    $notificationMessage = "Task updated and notification sent to {$user->name}!";
                } else {
                    $notificationMessage = "Task updated! Note: {$user->name} hasn't enabled notifications yet. They need to log in to receive notifications.";
                }
            } catch (\Exception $e) {
                Log::error('Failed to send notification on task update', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage()
                ]);
                $notificationMessage = "Task updated! Notification could not be sent.";
            }
        }

        // Send notification if task status changed to completed
        if ($fcmService && $validated['status'] === 'completed' && $task->assigned_to) {
            try {
                $user = $task->assignedUser;
                if ($user && $user->fcm_token) {
                    $this->sendTaskStatusNotification($task, 'completed', $fcmService);
                    if (!$notificationSent) {
                        $notificationSent = true;
                        $notificationMessage = "Task marked as completed and notification sent to {$user->name}!";
                    } else {
                        $notificationMessage = "Task updated, assignment and completion notifications sent to {$user->name}!";
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send status notification', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return redirect()->route('tasks.index')
            ->with('success', $notificationMessage)
            ->with('notification_sent', $notificationSent);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $task->delete();
        return redirect()->route('tasks.index')
            ->with('success', 'Task deleted successfully!');
    }

    /**
     * Send notification when task is assigned
     */
    private function sendTaskAssignmentNotification(Task $task, FcmService $fcmService): void
    {
        try {
            $user = $task->assignedUser;
            
            if (!$user || !$user->fcm_token) {
                Log::info('Task assignment notification skipped: User has no FCM token', [
                    'task_id' => $task->id,
                    'user_id' => $user->id ?? null
                ]);
                return;
            }

            $title = 'New Task Assigned';
            $body = "You have been assigned a new task: {$task->title}";
            
            $dueDateText = $task->due_date 
                ? $task->due_date->format('M d, Y') 
                : 'No due date';

            $data = [
                'type' => 'task_assigned',
                'task_id' => (string) $task->id,
                'task_title' => $task->title,
                'priority' => $task->priority,
                'due_date' => $dueDateText,
            ];

            $fcmService->sendToToken(
                $user->fcm_token,
                $title,
                $body,
                $data
            );

            Log::info('Task assignment notification sent', [
                'task_id' => $task->id,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send task assignment notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw exception - notification failure shouldn't break task creation
        }
    }

    /**
     * Send notification when task status changes
     */
    private function sendTaskStatusNotification(Task $task, string $status, FcmService $fcmService): void
    {
        try {
            $user = $task->assignedUser;
            
            if (!$user || !$user->fcm_token) {
                return;
            }

            $title = 'Task Status Updated';
            $body = match($status) {
                'completed' => "Task completed: {$task->title}",
                'cancelled' => "Task cancelled: {$task->title}",
                default => "Task status updated: {$task->title}",
            };

            $data = [
                'type' => 'task_status_update',
                'task_id' => (string) $task->id,
                'task_title' => $task->title,
                'status' => $status,
            ];

            $fcmService->sendToToken(
                $user->fcm_token,
                $title,
                $body,
                $data
            );
        } catch (\Exception $e) {
            Log::error('Failed to send task status notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * API endpoint to get tasks (for AJAX requests)
     */
    public function apiIndex()
    {
        $tasks = Task::with(['assignedUser', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }
}
