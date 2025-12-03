<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FcmController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

// Public Routes
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('tasks.index');
    }
    return redirect()->route('login');
});

// Firebase Config for Service Worker (Public)
Route::get('/firebase-config.js', function () {
    $config = [
        'apiKey' => config('services.firebase.api_key'),
        'authDomain' => config('services.firebase.auth_domain'),
        'projectId' => config('services.firebase.project_id'),
        'storageBucket' => config('services.firebase.storage_bucket'),
        'messagingSenderId' => config('services.firebase.messaging_sender_id'),
        'appId' => config('services.firebase.app_id'),
        'measurementId' => config('services.firebase.measurement_id'),
    ];
    
    return response('const firebaseConfig = ' . json_encode($config) . ';')
        ->header('Content-Type', 'application/javascript');
});

// Authentication Routes (Guest Only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

// Logout (Authenticated Only)
Route::middleware('auth')->post('/logout', [LoginController::class, 'logout'])->name('logout');

// FCM API Routes (Protected)
Route::middleware('auth')->prefix('fcm')->group(function () {
    // Update device token for a user
    Route::put('update-device-token', [FcmController::class, 'updateDeviceToken']);
    
    // Send notification to a user by user ID
    Route::post('send-notification', [FcmController::class, 'sendFcmNotification']);
    
    // Send notification to a specific device token
    Route::post('send-to-token', [FcmController::class, 'sendToToken']);
    
    // Send notification to multiple users
    Route::post('send-bulk', [FcmController::class, 'sendBulkNotification']);
    
    // Send notification to a topic
    Route::post('send-to-topic', [FcmController::class, 'sendToTopic']);
});

// Backward compatibility routes (Protected)
Route::middleware('auth')->group(function () {
    Route::put('update-device-token', [FcmController::class, 'updateDeviceToken']);
    Route::post('send-fcm-notification', [FcmController::class, 'sendFcmNotification']);
});

// Task Management Routes (Protected)
Route::middleware('auth')->group(function () {
    Route::resource('tasks', \App\Http\Controllers\TaskController::class);
    Route::get('api/tasks', [\App\Http\Controllers\TaskController::class, 'apiIndex'])->name('tasks.api.index');
    Route::get('tasks/notifications/test', function () {
        return view('tasks.notification-test');
    })->name('tasks.notifications.test');
});
