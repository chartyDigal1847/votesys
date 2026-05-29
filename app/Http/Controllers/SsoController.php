<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoController extends Controller
{
    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
            'embedded' => 'sometimes|boolean',
        ]);

        $authServiceUrl = rtrim((string) config('votesys.auth_service_url', config('app.portal_url', 'https://deoris.test')), '/');
        $http = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$validated['token'],
        ])->timeout(8);

        if (! config('votesys.auth_verify_ssl', true)) {
            $http = $http->withoutVerifying();
        }

        try {
            $response = $http->post($authServiceUrl.'/api/v1/sso/exchange', [
                'token' => $validated['token'],
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('VoteSys SSO exchange service unavailable.', [
                'auth_service_url' => $authServiceUrl,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'SSO service is unavailable from this PC. Check the portal URL or local HTTPS certificate.',
            ], 503);
        }

        if (! $response->ok()) {
            return response()->json(['success' => false, 'message' => 'Invalid SSO token'], 401);
        }

        $payload = $response->json();
        $user = $payload['user'] ?? $payload['data']['user'] ?? null;
        if (!is_array($user) || empty($user['id'])) {
            return response()->json(['success' => false, 'message' => 'Invalid SSO response'], 401);
        }

        $request->session()->put([
            'sso_id' => (string) $user['id'],
            'sso_name' => (string) ($user['name'] ?? ''),
            'sso_email' => strtolower((string) ($user['email'] ?? '')),
            'sso_role' => (string) ($user['role'] ?? 'student'),
            'sso_embedded' => (bool) ($validated['embedded'] ?? false),
            'sso_authenticated_at' => now()->timestamp,
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => (string) $user['id'],
                'name' => (string) ($user['name'] ?? ''),
                'email' => strtolower((string) ($user['email'] ?? '')),
                'role' => (string) ($user['role'] ?? 'student'),
            ],
        ]);
    }
}
