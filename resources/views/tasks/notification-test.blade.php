<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ Auth::id() }}">
    <title>Test Notifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .section h2 {
            margin-bottom: 15px;
            color: #333;
        }

        .status {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-right: 10px;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .info-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.9em;
            word-break: break-all;
        }

        .info-item strong {
            display: block;
            margin-bottom: 5px;
            color: #667eea;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Notification Test & Debug</h1>
            <p>Test and debug push notifications</p>
        </div>

        <!-- User Info Section -->
        <div class="section">
            <h2>Your User Information</h2>
            <div class="info-item">
                <strong>User ID:</strong> {{ Auth::id() }}
            </div>
            <div class="info-item">
                <strong>Name:</strong> {{ Auth::user()->name }}
            </div>
            <div class="info-item">
                <strong>Email:</strong> {{ Auth::user()->email }}
            </div>
            <div class="info-item">
                <strong>FCM Token:</strong> 
                @if(Auth::user()->fcm_token)
                    <span style="color: green;">✅ Registered</span>
                    <div style="margin-top: 5px; font-size: 0.8em; color: #666;">
                        {{ Str::limit(Auth::user()->fcm_token, 50) }}...
                    </div>
                @else
                    <span style="color: red;">❌ Not Registered</span>
                    <div style="margin-top: 10px;">
                        <a href="/fcm-test" class="btn" style="text-decoration: none; display: inline-block;">Get FCM Token</a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Test Notification Section -->
        <div class="section">
            <h2>Test Notification</h2>
            @if(!Auth::user()->fcm_token)
                <div class="status warning">
                    ⚠️ You don't have an FCM token registered. Please visit <a href="/fcm-test" style="color: #667eea;">/fcm-test</a> to get your token first.
                </div>
            @else
                <div class="status info">
                    ✅ FCM token is registered. Click the button below to send a test notification.
                </div>
                <button class="btn" onclick="sendTestNotification()">Send Test Notification</button>
                <div id="test-result"></div>
            @endif
        </div>

        <!-- Manual Test Section -->
        <div class="section">
            <h2>Manual Test via API</h2>
            <p>You can also test by creating/assigning a task, or use the API directly:</p>
            <div class="info-item">
                <strong>Endpoint:</strong> POST /fcm/send-notification<br>
                <strong>Body:</strong> {<br>
                &nbsp;&nbsp;"user_id": {{ Auth::id() }},<br>
                &nbsp;&nbsp;"title": "Test Notification",<br>
                &nbsp;&nbsp;"body": "This is a test message"<br>
                }
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="section">
            <h2>Troubleshooting</h2>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>✅ Make sure FCM token is registered (see above)</li>
                <li>✅ Check browser notification permissions are granted</li>
                <li>✅ Verify <code>FCM_PROJECT_ID</code> is set in <code>.env</code></li>
                <li>✅ Ensure Firebase service account JSON is in <code>storage/app/json/file.json</code></li>
                <li>✅ Check Laravel logs: <code>storage/logs/laravel.log</code></li>
                <li>✅ Make sure you've initialized Firebase on the <a href="/fcm-test">test page</a></li>
            </ul>
        </div>

        <a href="{{ route('tasks.index') }}" class="back-link">← Back to Tasks</a>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const userId = document.querySelector('meta[name="user-id"]').content;

        async function sendTestNotification() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.innerHTML = '<div class="status info">Sending test notification...</div>';

            try {
                const response = await fetch('/fcm/send-notification', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: parseInt(userId),
                        title: 'Test Notification',
                        body: 'This is a test notification from the Task Management System!'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    resultDiv.innerHTML = '<div class="status success">✅ Notification sent successfully! Check your device for the notification.</div>';
                } else {
                    resultDiv.innerHTML = '<div class="status error">❌ Failed: ' + data.message + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="status error">❌ Error: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>

