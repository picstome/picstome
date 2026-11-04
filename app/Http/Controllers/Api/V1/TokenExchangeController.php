<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExchangeTokenRequest;
use App\Models\ExternalAuthorization;
use App\Traits\Concerns\RespondsWithApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Exchange a single-use browser authorization code for a long-lived Sanctum
 * token. The endpoint is public but throttled because the code is the secret.
 */
class TokenExchangeController extends Controller
{
    use RespondsWithApiErrors;

    public function __invoke(ExchangeTokenRequest $request): JsonResponse
    {
        $clientName = (string) $request->string('client_name');
        $authorization = ExternalAuthorization::findByCode((string) $request->string('code'));

        if (! $authorization || $authorization->client_name !== $clientName) {
            return $this->apiError('invalid_code', __('The authorization code is invalid.'), 400);
        }

        if ($authorization->isConsumed()) {
            return $this->apiError('invalid_code', __('The authorization code has already been used.'), 400);
        }

        if ($authorization->isExpired()) {
            return $this->apiError('expired_code', __('The authorization code has expired.'), 410);
        }

        $consumed = ExternalAuthorization::query()
            ->whereKey($authorization->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if (! $consumed) {
            return $this->apiError('invalid_code', __('The authorization code has already been used.'), 400);
        }

        $token = $authorization->user->createToken(
            $clientName.'-'.Str::random(6),
            ['sync'],
            now()->addDays((int) config('external_api.token_days', 365)),
        );

        Log::info('external.auth.exchange', [
            'user_id' => $authorization->user_id,
            'client_name' => $clientName,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            // Desktop builds predate the generic contract and still read
            // `authkey`. Keeping the alias costs nothing for new clients.
            'authkey' => $token->plainTextToken,
            'email' => $authorization->user->email,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ]);
    }
}
