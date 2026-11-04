<?php

namespace App\Http\Controllers;

use App\Models\ExternalAuthorization;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Browser-side half of the external client authorization flow.
 *
 * The client opens `/authorize?nonce=...&client_name=...`. After the user
 * confirms, the server redirects to the client deep link with a single-use
 * code that can be exchanged for a Sanctum token.
 */
class ExternalAuthorizationController extends Controller
{
    public function show(Request $request): View
    {
        $payload = $this->validatedPayload($request);

        return view('external.authorize', [
            'email' => $request->user()->email,
            'clientName' => $payload['client_name'],
            'nonce' => $payload['nonce'],
            'noncePrefix' => Str::substr($payload['nonce'], 0, 8),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validatedPayload($request);

        $code = Str::random(48);

        ExternalAuthorization::create([
            'user_id' => $request->user()->id,
            'client_name' => $payload['client_name'],
            'code_hash' => hash('sha256', $code),
            'nonce' => $payload['nonce'],
            'expires_at' => now()->addMinutes((int) config('external_api.code_ttl_minutes', 5)),
        ]);

        return redirect()->away(
            'picstome://auth?code='.rawurlencode($code).'&state='.rawurlencode($payload['nonce'])
        );
    }

    public function cancel(): View
    {
        return view('external.authorize-cancelled');
    }

    /**
     * @return array{nonce: string, client_name: string}
     */
    protected function validatedPayload(Request $request): array
    {
        $validator = Validator::make($request->only(['nonce', 'client_name']), [
            'nonce' => ['required', 'string', 'min:43', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'client_name' => ['required', 'string', 'min:1', 'max:64', 'regex:/^[A-Za-z0-9 _-]+$/'],
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(
                response()->view('external.authorize-invalid', [
                    'errors' => $validator->errors(),
                ], 422)
            );
        }

        return [
            'nonce' => (string) $request->input('nonce'),
            'client_name' => (string) $request->input('client_name'),
        ];
    }
}
