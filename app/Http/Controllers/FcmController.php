<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FcmService;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class FcmController extends Controller
{
    protected FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Update device token for a user
     */
    public function updateDeviceToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'fcm_token' => 'required|string|min:50|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::find($request->user_id);
            
            // Basic token validation (FCM tokens are long strings)
            $token = $request->fcm_token;
            if (strlen($token) < 50 || strlen($token) > 200) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid FCM token format'
                ], 400);
            }

            $user->update(['fcm_token' => $token]);

            return response()->json([
                'success' => true,
                'message' => 'Device token updated successfully',
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update device token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send FCM notification to a user by user ID
     */
    public function sendFcmNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'data' => 'sometimes|array',
            'image' => 'sometimes|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::find($request->user_id);
            $fcmToken = $user->fcm_token;

            if (!$fcmToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have a device token. Please register the device token first.'
                ], 400);
            }

            $title = $request->title;
            $body = $request->body;
            $data = $request->input('data', []);

            // Prepare options for image if provided
            $options = [];
            if ($request->has('image')) {
                $options['webpush'] = [
                    'notification' => [
                        'image' => $request->image
                    ]
                ];
                $options['android'] = [
                    'notification' => [
                        'image' => $request->image
                    ]
                ];
                $options['apns'] = [
                    'payload' => [
                        'aps' => [
                            'mutable-content' => 1,
                            'alert' => [
                                'title' => $title,
                                'body' => $body
                            ]
                        ]
                    ],
                    'fcm_options' => [
                        'image' => $request->image
                    ]
                ];
            }

            $response = $this->fcmService->sendToToken($fcmToken, $title, $body, $data, $options);

            return response()->json([
                'success' => true,
                'message' => 'Notification has been sent successfully',
                'response' => $response,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendBulkNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $users = User::whereIn('id', $request->user_ids)
                ->whereNotNull('fcm_token')
                ->get();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users found with FCM tokens'
                ], 400);
            }

            $tokens = $users->pluck('fcm_token')->toArray();
            $title = $request->title;
            $body = $request->body;
            $data = $request->input('data', []);

            $results = $this->fcmService->sendToMultipleTokens($tokens, $title, $body, $data);

            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failureCount = count($results) - $successCount;

            return response()->json([
                'success' => true,
                'message' => "Notifications sent. Success: {$successCount}, Failed: {$failureCount}",
                'total' => count($results),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to a topic
     */
    public function sendToTopic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $response = $this->fcmService->sendToTopic(
                $request->topic,
                $request->title,
                $request->body,
                $request->input('data', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification sent to topic successfully',
                'topic' => $request->topic,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification to topic: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send notification to a specific device token (direct)
     */
    public function sendToToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|min:50|max:200',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $response = $this->fcmService->sendToToken(
                $request->token,
                $request->title,
                $request->body,
                $request->input('data', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully',
                'response' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage()
            ], 500);
        }
    }
}
