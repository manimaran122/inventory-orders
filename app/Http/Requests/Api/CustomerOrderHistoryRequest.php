<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates GET /api/customers/orders?email=...
 */
class CustomerOrderHistoryRequest extends FormRequest
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
            // Pagination is bounded so a caller cannot request an enormous
            // page and exhaust memory.
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required to look up order history.',
            'email.email' => 'A valid email address is required.',
        ];
    }

    public function email(): string
    {
        return trim((string) $this->validated('email'));
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }
}
