<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .header h1 {
            color: #333;
            font-size: 2em;
        }

        .badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }

        .task-details {
            margin-bottom: 30px;
        }

        .detail-section {
            margin-bottom: 25px;
        }

        .detail-section h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .detail-section p {
            color: #666;
            line-height: 1.8;
            font-size: 1.1em;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .meta-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .meta-item strong {
            display: block;
            color: #667eea;
            margin-bottom: 5px;
        }

        .meta-item span {
            color: #333;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
            margin-right: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $task->title }}</h1>
            <div>
                <span class="badge badge-{{ $task->status_badge_color }}">
                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                </span>
            </div>
        </div>

        <div class="task-details">
            @if($task->description)
                <div class="detail-section">
                    <h3>Description</h3>
                    <p>{{ $task->description }}</p>
                </div>
            @endif

            <div class="meta-grid">
                <div class="meta-item">
                    <strong>Priority</strong>
                    <span class="badge badge-{{ $task->priority_badge_color }}">
                        {{ ucfirst($task->priority) }}
                    </span>
                </div>

                <div class="meta-item">
                    <strong>Assigned To</strong>
                    <span>{{ $task->assignedUser->name ?? 'Unassigned' }}</span>
                    @if($task->assignedUser)
                        <br><small style="color: #666;">{{ $task->assignedUser->email }}</small>
                    @endif
                </div>

                <div class="meta-item">
                    <strong>Created By</strong>
                    <span>{{ $task->creator->name ?? 'N/A' }}</span>
                </div>

                @if($task->due_date)
                    <div class="meta-item">
                        <strong>Due Date</strong>
                        <span>{{ $task->due_date->format('F d, Y \a\t g:i A') }}</span>
                    </div>
                @endif

                <div class="meta-item">
                    <strong>Created</strong>
                    <span>{{ $task->created_at->format('F d, Y \a\t g:i A') }}</span>
                </div>

                <div class="meta-item">
                    <strong>Last Updated</strong>
                    <span>{{ $task->updated_at->format('F d, Y \a\t g:i A') }}</span>
                </div>
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('tasks.edit', $task) }}" class="btn btn-primary">Edit Task</a>
            <a href="{{ route('tasks.index') }}" class="btn btn-secondary">Back to Tasks</a>
            <form action="{{ route('tasks.destroy', $task) }}" method="POST" style="display: inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this task?')">Delete Task</button>
            </form>
        </div>
    </div>
</body>
</html>

