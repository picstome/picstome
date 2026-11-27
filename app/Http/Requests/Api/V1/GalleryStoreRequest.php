<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Validation for galleries created by external clients.
 *
 * `expiration_date` follows three rules:
 * - omitted: the server applies one month;
 * - a date: it must be between today and one year from now;
 * - explicit null: never expires, only when the team has unlimited retention.
 */
class GalleryStoreRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $team = $this->team();

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'photoshoot_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('photoshoots', 'id')
                    ->where(fn ($query) => $query->where('team_id', $team->id)),
            ],
            'expiration_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.$this->maxExpirationDate()->toDateString(),
            ],
            'keep_original_size' => ['sometimes', 'boolean'],
        ];
    }

    public function team(): Team
    {
        /** @var Team $team */
        $team = $this->user()->currentTeam;

        return $team;
    }

    public function maxExpirationDate(): CarbonInterface
    {
        return now()->addYear();
    }

    /**
     * Expiration stored on the new gallery.
     *
     * An absent field gets one month, an explicit null stays null (validated by
     * `expirationNotAllowedResponse()`), and a date is parsed as a civil date.
     */
    public function expirationDate(): ?CarbonInterface
    {
        $validated = $this->validated();

        if (! array_key_exists('expiration_date', $validated)) {
            return now()->addMonth();
        }

        if ($validated['expiration_date'] === null) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $validated['expiration_date'])->startOfDay();
    }

    /**
     * Reject an explicit null when the plan cannot keep galleries forever.
     */
    public function expirationNotAllowedResponse(): ?JsonResponse
    {
        $validated = $this->validated();

        $wantsNoExpiration = array_key_exists('expiration_date', $validated)
            && $validated['expiration_date'] === null;

        if (! $wantsNoExpiration || $this->team()->hasUnlimitedRetention()) {
            return null;
        }

        $max = $this->maxExpirationDate()->toDateString();
        $message = __('Your plan does not allow galleries without an expiration date. The latest date is :date.', [
            'date' => $max,
        ]);

        return response()->json([
            'message' => $message,
            'errors' => ['expiration_date' => [$message]],
            'code' => 'validation_error',
            'max_expiration_date' => $max,
        ], 422);
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        $response = [
            'message' => 'The given data was invalid.',
            'errors' => $errors,
            'code' => 'validation_error',
        ];

        // Clients can correct an out-of-range date without guessing the
        // retention limit of the plan.
        if ($errors->has('expiration_date')) {
            $response['max_expiration_date'] = $this->maxExpirationDate()->toDateString();
        }

        throw new HttpResponseException(response()->json($response, 422));
    }
}
