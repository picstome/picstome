<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

class ExchangeTokenRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9 _-]+$/'],
        ];
    }
}
