<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/auth/token.
 */
class IssueTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function deviceName(): string
    {
        return (string) ($this->validated('device_name') ?? 'api-client');
    }
}
