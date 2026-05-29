<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoController extends Controller
{
    private function debugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        try {
            $payload = json_encode([
                'sessionId' => '0cc008',
                'runId' => 'run6',
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) floor(microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return;
            }
            file_put_contents('C:/xampp/htdocs/deoris/debug-0cc008.log', $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Ignore debug log failures.
        }
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
            'embedded' => 'sometimes|boolean',
        ]);
        // #region agent log
        $this->debugLog('H18', 'VoteSys\\SsoController::exchange:entry', 'votesys exchange called', [
            'hasToken' => !empty($validated['token']),
            'embedded' => (bool) ($validated['embedded'] ?? false),
            'sessionId' => $request->session()->getId(),
        ]);
        // #endregion

        $authServiceUrl = rtrim((string) config('votesys.auth_service_url', config('app.portal_url', 'https://deoris.test')), '/');
        $http = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$validated['token'],
        ])->timeout(8);

        if (! config('votesys.auth_verify_ssl', true)) {
            $http = $http->withoutVerifying();
        }

        $maxAttempts = 3;
        $attempt = 0;
        $response = null;

        // #region agent log
        $this->debugLog('H47', 'VoteSys\\SsoController::exchange:exchangeStart', 'votesys portal exchange started', [
            'exchangeUrl' => $authServiceUrl.'/api/v1/sso/exchange',
            'maxAttempts' => $maxAttempts,
        ]);
        // #endregion

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $response = $http->post($authServiceUrl.'/api/v1/sso/exchange', [
                    'token' => $validated['token'],
                ]);
            } catch (ConnectionException $exception) {
                // #region agent log
                $this->debugLog('H47', 'VoteSys\\SsoController::exchange:connectionError', 'votesys portal exchange connection exception', [
                    'attempt' => $attempt,
                    'error' => $exception->getMessage(),
                ]);
                // #endregion
                if ($attempt < $maxAttempts) {
                    continue;
                }

                Log::warning('VoteSys SSO exchange service unavailable.', [
                    'auth_service_url' => $authServiceUrl,
                    'error' => $exception->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'SSO service is temporarily unavailable.',
                ], 503);
            }

            $status = $response->status();
            $retryable = $status === 429 || $status >= 500;
            // #region agent log
            $this->debugLog('H47', 'VoteSys\\SsoController::exchange:portalResponse', 'votesys portal exchange response', [
                'attempt' => $attempt,
                'status' => $status,
                'ok' => $response->ok(),
                'retryable' => $retryable,
            ]);
            // #endregion

            if ($response->ok() || ! $retryable) {
                break;
            }
        }

        if (! $response || ! $response->ok()) {
            $status = $response?->status() ?? 500;
            // #region agent log
            $this->debugLog('H47', 'VoteSys\\SsoController::exchange:exchangeFailed', 'votesys exchange failed after retries', [
                'status' => $status,
                'attempts' => $attempt,
            ]);
            // #endregion

            if ($status === 401 || $status === 422) {
                return response()->json(['success' => false, 'message' => 'Invalid SSO token'], 401);
            }

            if ($status === 429 || $status >= 500) {
                return response()->json(['success' => false, 'message' => 'Portal SSO temporarily unavailable'], 503);
            }

            return response()->json(['success' => false, 'message' => 'Portal SSO exchange failed'], 502);
        }

        $payload = $response->json();
        $user = $payload['user'] ?? $payload['data']['user'] ?? null;
        if (!is_array($user) || empty($user['id'])) {
            // #region agent log
            $this->debugLog('H18', 'VoteSys\\SsoController::exchange:invalidPayload', 'votesys payload missing user id', [
                'hasUserArray' => is_array($user),
            ]);
            // #endregion
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
        // #region agent log
        $this->debugLog('H18', 'VoteSys\\SsoController::exchange:sessionHydrated', 'votesys session hydrated after exchange', [
            'ssoId' => (string) $user['id'],
            'role' => (string) ($user['role'] ?? 'student'),
            'sessionId' => $request->session()->getId(),
        ]);
        // #endregion

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
