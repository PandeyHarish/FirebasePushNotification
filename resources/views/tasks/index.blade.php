<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Task Management System</title>
    @auth
        <meta name="user-id" content="{{ Auth::id() }}">
    @endauth
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
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 2.5em;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .tasks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .task-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid #667eea;
        }

        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .task-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .task-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .task-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }

        .task-info {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .task-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .task-actions .btn {
            padding: 8px 16px;
            font-size: 0.9em;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            color: #666;
        }

        .empty-state h2 {
            margin-bottom: 10px;
            color: #333;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a, .pagination span {
            padding: 10px 15px;
            background: white;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
            border: 1px solid #e0e0e0;
        }

        .pagination .active {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            .tasks-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js"></script>
    <script>
        // Firebase configuration
        const firebaseConfig = {
            apiKey: "{{ config('services.firebase.api_key') }}",
            authDomain: "{{ config('services.firebase.auth_domain') }}",
            projectId: "{{ config('services.firebase.project_id') }}",
            storageBucket: "{{ config('services.firebase.storage_bucket') }}",
            messagingSenderId: "{{ config('services.firebase.messaging_sender_id') }}",
            appId: "{{ config('services.firebase.app_id') }}",
            measurementId: "{{ config('services.firebase.measurement_id') }}"
        };

        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);

        const userId = {{ Auth::id() }};
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // Initialize notifications on page load
        async function initializeNotifications() {
            if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                console.log('Notifications not supported in this browser');
                return;
            }

            try {
                // Step 1: Register service worker
                const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
                console.log('Service Worker registered:', registration);

                // Step 2: Initialize Firebase Messaging
                const messaging = firebase.messaging();

                // Step 3: Request notification permission automatically after login
                // Browsers may block this if not triggered by user interaction, so we try anyway
                let permission = Notification.permission;
                
                if (permission === 'default') {
                    console.log('Requesting notification permission automatically...');
                    try {
                        // Try to request permission (may not work in all browsers without user click)
                        permission = await Notification.requestPermission();
                    } catch (e) {
                        console.log('Auto-permission request failed, will request on user interaction:', e);
                    }
                }
                
                if (permission === 'granted') {
                    console.log('✅ Notification permission granted!');
                    await getAndSaveToken(messaging);
                } else if (permission === 'denied') {
                    console.log('⚠️ Notification permission denied. User can enable in browser settings.');
                } else {
                    // Permission is still 'default' - show a message
                    console.log('ℹ️ Notification permission not yet granted. Browser will ask on first user interaction.');
                    // Try to get token anyway (some browsers allow this)
                    try {
                        await getAndSaveToken(messaging);
                    } catch (e) {
                        console.log('Could not get token without permission:', e);
                    }
                }

                // Step 4: Listen for foreground messages
                messaging.onMessage((payload) => {
                    console.log('Message received:', payload);
                    
                    // Show browser notification
                    if (Notification.permission === 'granted') {
                        const notificationTitle = payload.notification?.title || 'New Notification';
                        const notificationOptions = {
                            body: payload.notification?.body || 'You have a new message',
                            icon: payload.notification?.icon || '/firebase-logo.png',
                            badge: '/firebase-logo.png',
                            data: payload.data,
                            tag: 'task-notification'
                        };
                        
                        new Notification(notificationTitle, notificationOptions);
                    }
                });

            } catch (error) {
                console.error('Error initializing notifications:', error);
            }
        }

        // Get FCM token and save to database
        async function getAndSaveToken(messaging) {
            try {
                const token = await messaging.getToken();
                
                if (token) {
                    console.log('FCM Token:', token);
                    
                    // Save token to database
                    try {
                        const response = await fetch('/fcm/update-device-token', {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                user_id: userId,
                                fcm_token: token
                            })
                        });

                        const data = await response.json();
                        
                        if (data.success) {
                            console.log('FCM token saved successfully');
                        } else {
                            console.error('Failed to save FCM token:', data.message);
                        }
                    } catch (error) {
                        console.error('Error saving FCM token:', error);
                    }
                } else {
                    console.log('No FCM token available');
                }
            } catch (error) {
                console.error('Error getting FCM token:', error);
            }
        }

        // Initialize on page load - runs automatically after login
        window.addEventListener('load', function() {
            // Initialize immediately after page loads
            console.log('Page loaded, initializing notifications...');
            initializeNotifications();
        });

        // Also try on DOMContentLoaded as backup
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up notifications...');
            // Slight delay to ensure everything is ready
            setTimeout(initializeNotifications, 500);
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📋 Task Management System</h1>
                <p>Welcome, {{ Auth::user()->name }}! Manage your tasks with real-time notifications</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <a href="{{ route('tasks.create') }}" class="btn">+ Create New Task</a>
                <a href="{{ route('tasks.notifications.test') }}" class="btn" style="background: rgba(255,255,255,0.3); border: 1px solid white;">🔔 Test Notifications</a>
                <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn" style="background: rgba(255,255,255,0.2); border: 1px solid white;">Logout</button>
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="alert {{ session('notification_sent') ? 'alert-success' : (str_contains(session('success'), 'Note:') ? 'alert-warning' : 'alert-info') }}">
                @if(session('notification_sent'))
                    🔔 {{ session('success') }}
                @elseif(str_contains(session('success'), 'Note:'))
                    ⚠️ {{ session('success') }}
                @else
                    ✅ {{ session('success') }}
                @endif
            </div>
        @endif

        @if($tasks->count() > 0)
            <div class="tasks-grid">
                @foreach($tasks as $task)
                    <div class="task-card">
                        <div class="task-header">
                            <div>
                                <div class="task-title">{{ $task->title }}</div>
                            </div>
                            <span class="badge badge-{{ $task->status_badge_color }}">
                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                            </span>
                        </div>

                        @if($task->description)
                            <div class="task-description">{{ $task->description }}</div>
                        @endif

                        <div class="task-meta">
                            <span class="badge badge-{{ $task->priority_badge_color }}">
                                Priority: {{ ucfirst($task->priority) }}
                            </span>
                            @if($task->assignedUser)
                                <span class="badge badge-info">
                                    Assigned to: {{ $task->assignedUser->name }}
                                </span>
                            @else
                                <span class="badge badge-secondary">Unassigned</span>
                            @endif
                            @if($task->due_date)
                                <span class="badge badge-warning">
                                    Due: {{ $task->due_date->format('M d, Y') }}
                                </span>
                            @endif
                        </div>

                        <div class="task-info">
                            <strong>Created by:</strong> {{ $task->creator->name ?? 'N/A' }}<br>
                            <strong>Created:</strong> {{ $task->created_at->format('M d, Y H:i') }}
                        </div>

                        <div class="task-actions">
                            <a href="{{ route('tasks.show', $task) }}" class="btn btn-info">View</a>
                            <a href="{{ route('tasks.edit', $task) }}" class="btn">Edit</a>
                            <form action="{{ route('tasks.destroy', $task) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pagination">
                {{ $tasks->links() }}
            </div>
        @else
            <div class="empty-state">
                <h2>No tasks yet</h2>
                <p>Get started by creating your first task!</p>
                <a href="{{ route('tasks.create') }}" class="btn" style="margin-top: 20px;">Create Your First Task</a>
            </div>
        @endif
    </div>
</body>
</html>

