<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="user-id" content="{{ Auth::id() }}">
    @endauth
    <title>@yield('title', 'Task Management System')</title>
    
    @stack('styles')
    
    <!-- Firebase SDK for Notifications -->
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

        @auth
        const userId = {{ Auth::id() }};
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // Initialize notifications automatically after login
        async function initializeNotifications() {
            if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                console.log('Notifications not supported in this browser');
                return;
            }

            try {
                // Step 1: Register service worker
                const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
                console.log('✅ Service Worker registered');

                // Step 2: Initialize Firebase Messaging
                const messaging = firebase.messaging();

                // Step 3: Request notification permission if not already set
                if (Notification.permission === 'default') {
                    console.log('Requesting notification permission...');
                    const permission = await Notification.requestPermission();
                    
                    if (permission === 'granted') {
                        console.log('✅ Notification permission granted!');
                        await getAndSaveToken(messaging);
                    } else if (permission === 'denied') {
                        console.log('⚠️ Notification permission denied');
                    }
                } else if (Notification.permission === 'granted') {
                    // Permission already granted, just get and save the token
                    console.log('✅ Notification permission already granted');
                    await getAndSaveToken(messaging);
                }

                // Step 4: Listen for foreground messages
                messaging.onMessage((payload) => {
                    console.log('📬 Message received:', payload);
                    
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
                console.error('❌ Error initializing notifications:', error);
            }
        }

        // Get FCM token and save to database automatically
        async function getAndSaveToken(messaging) {
            try {
                const token = await messaging.getToken();
                
                if (token) {
                    console.log('📱 FCM Token generated:', token.substring(0, 50) + '...');
                    
                    // Automatically save token to database
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
                            console.log('✅ FCM token saved to database automatically');
                        } else {
                            console.error('❌ Failed to save FCM token:', data.message);
                        }
                    } catch (error) {
                        console.error('❌ Error saving FCM token:', error);
                    }
                } else {
                    console.log('⚠️ No FCM token available');
                }
            } catch (error) {
                console.error('❌ Error getting FCM token:', error);
            }
        }

        // Initialize notifications when page loads (after login/registration)
        window.addEventListener('load', function() {
            // Delay to ensure page is fully loaded
            setTimeout(initializeNotifications, 500);
        });
        @endauth
    </script>
</head>
<body>
    @yield('content')
    
    @stack('scripts')
</body>
</html>

