<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private string $projectId;
    private string $credentialsFilePath;
    private ?string $accessToken = null;

    public function __construct()
    {
        $projectId = config('services.fcm.project_id');
        
        if (!$projectId) {
            throw new \Exception('FCM project ID is not configured. Please set FCM_PROJECT_ID in your .env file.');
        }

        $this->projectId = $projectId;

        // Check multiple possible locations for credentials
        $credentialsFileName = config('services.fcm.credentials_file', 'firebase_auth.json');
        
        // Try multiple paths using base_path() directly (more reliable than Storage::path())
        $possiblePaths = [
            base_path('storage/app/json/' . $credentialsFileName),
            base_path('storage/app/' . $credentialsFileName),
            base_path('storage/app/firebase_auth.json'),
        ];
        
        $credentialsPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $credentialsPath = $path;
                break;
            }
        }
        
        if (!$credentialsPath) {
            $checkedPaths = array_map(function($path) {
                return str_replace(base_path(), '[project]', $path);
            }, $possiblePaths);
            throw new \Exception('Firebase credentials file not found. Expected at: ' . base_path('storage/app/firebase_auth.json') . '. You need to download the Service Account JSON from Firebase Console → Project Settings → Service Accounts → Generate new private key');
        }
        
        $this->credentialsFilePath = $credentialsPath;
        
        // Validate it's a Service Account JSON, not client config
        $fileContent = file_get_contents($this->credentialsFilePath);
        $json = json_decode($fileContent, true);
        
        if (!$json || !isset($json['type']) || $json['type'] !== 'service_account') {
            throw new \Exception('Invalid Firebase credentials file. The file must be a Service Account JSON (with "type": "service_account"), not the client-side config. Download it from Firebase Console → Project Settings → Service Accounts → Generate new private key');
        }
        
        // Validate that private key is not a placeholder
        if (isset($json['private_key']) && (
            str_contains($json['private_key'], 'YOUR_PRIVATE_KEY') || 
            str_contains($json['private_key'], 'PLACEHOLDER') ||
            strlen($json['private_key']) < 100
        )) {
            throw new \Exception('The Firebase credentials file contains placeholder values. Please download the REAL Service Account JSON from Firebase Console → Project Settings → Service Accounts → "Generate new private key". The current file has placeholder private key values.');
        }
        
        // Validate required fields exist
        $requiredFields = ['private_key', 'client_email', 'project_id'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($json[$field]) || empty($json[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new \Exception('Firebase credentials file is missing required fields: ' . implode(', ', $missingFields) . '. Please download a complete Service Account JSON from Firebase Console.');
        }
    }

    /**
     * Get or refresh access token
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($this->credentialsFilePath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
            
            if (!isset($token['access_token'])) {
                throw new \Exception('No access token received from Google. Check your Service Account JSON credentials.');
            }
            
            $this->accessToken = $token['access_token'];
            return $this->accessToken;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Provide helpful error messages
            if (str_contains($errorMessage, 'OpenSSL') || str_contains($errorMessage, 'unable to validate key')) {
                $errorMessage = 'Invalid private key in Service Account JSON. Please download a NEW Service Account JSON from Firebase Console → Project Settings → Service Accounts → "Generate new private key" and replace the current file.';
            } elseif (str_contains($errorMessage, 'invalid_grant') || str_contains($errorMessage, 'invalid client')) {
                $errorMessage = 'Invalid Service Account credentials. The JSON file may be expired or incorrect. Download a new one from Firebase Console.';
            }
            
            Log::error('FCM: Failed to get access token', [
                'error' => $e->getMessage(),
                'credentials_file' => $this->credentialsFilePath
            ]);
            
            throw new \Exception('Failed to authenticate with Firebase: ' . $errorMessage);
        }
    }

    /**
     * Send notification to a single device token
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], array $options = []): array
    {
        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        // Add data payload if provided
        if (!empty($data)) {
            $message['data'] = $this->prepareDataPayload($data);
        }

        // Add Android specific options
        if (isset($options['android'])) {
            $message['android'] = $options['android'];
        }

        // Add APNS specific options (iOS)
        if (isset($options['apns'])) {
            $message['apns'] = $options['apns'];
        }

        // Add webpush specific options
        if (isset($options['webpush'])) {
            $message['webpush'] = $options['webpush'];
        }

        return $this->sendMessage($message);
    }

    /**
     * Send notification to multiple device tokens
     */
    public function sendToMultipleTokens(array $tokens, string $title, string $body, array $data = [], array $options = []): array
    {
        $results = [];
        
        foreach ($tokens as $token) {
            try {
                $result = $this->sendToToken($token, $title, $body, $data, $options);
                $results[] = [
                    'token' => $token,
                    'success' => true,
                    'response' => $result,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'token' => $token,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Send notification to a topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = [], array $options = []): array
    {
        $message = [
            'topic' => $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        if (!empty($data)) {
            $message['data'] = $this->prepareDataPayload($data);
        }

        if (isset($options['android'])) {
            $message['android'] = $options['android'];
        }

        if (isset($options['apns'])) {
            $message['apns'] = $options['apns'];
        }

        if (isset($options['webpush'])) {
            $message['webpush'] = $options['webpush'];
        }

        return $this->sendMessage($message);
    }

    /**
     * Send notification to a condition
     */
    public function sendToCondition(string $condition, string $title, string $body, array $data = [], array $options = []): array
    {
        $message = [
            'condition' => $condition,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        if (!empty($data)) {
            $message['data'] = $this->prepareDataPayload($data);
        }

        if (isset($options['android'])) {
            $message['android'] = $options['android'];
        }

        if (isset($options['apns'])) {
            $message['apns'] = $options['apns'];
        }

        if (isset($options['webpush'])) {
            $message['webpush'] = $options['webpush'];
        }

        return $this->sendMessage($message);
    }

    /**
     * Send message to FCM API
     */
    private function sendMessage(array $message): array
    {
        $accessToken = $this->getAccessToken();
        
        $payload = [
            'message' => $message,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $payload);

            if ($response->failed()) {
                $error = $response->json();
                Log::error('FCM: Failed to send message', [
                    'error' => $error,
                    'payload' => $payload,
                ]);
                throw new \Exception('FCM API error: ' . json_encode($error));
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('FCM: Exception while sending message', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }
    }

    /**
     * Prepare data payload (FCM requires string values)
     */
    private function prepareDataPayload(array $data): array
    {
        $prepared = [];
        
        foreach ($data as $key => $value) {
            // FCM data payload values must be strings
            if (is_array($value) || is_object($value)) {
                $prepared[$key] = json_encode($value);
            } else {
                $prepared[$key] = (string) $value;
            }
        }

        return $prepared;
    }

    /**
     * Validate FCM token
     */
    public function validateToken(string $token): bool
    {
        // Basic validation - FCM tokens are long strings
        return strlen($token) > 50 && strlen($token) < 200;
    }
}

