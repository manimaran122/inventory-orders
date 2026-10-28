<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates GET /api/products/low-stock?threshold=...
 *
 * The threshold is bounded on both sides:
 *  - min 0, because negative stock is impossible, so a negative threshold is
 *    a client bug rather than a meaningful query.
 *  - max (configurable, default 1000), so nobody can pass threshold=99999999
 *    and use this endpoint to dump the entire catalogue in one request.
 */
class LowStockRequest extends FormRequest
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
            'threshold' => [
                'sometimes',
                'integer',
                'min:0',
                'max:'.config('inventory.max_low_stock_threshold'),
            ],
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
            'threshold.integer' => 'The threshold must be a whole number.',
            'threshold.min' => 'The threshold may not be negative.',
            'threshold.max' => 'The threshold may not be greater than :max.',
        ];
    }

    /**
     * The requested threshold, falling back to the documented default.
     */
    public function threshold(): int
    {
        $threshold = $this->validated('threshold');

        if ($threshold === null) {
            return (int) config('inventory.low_stock_threshold');
        }

        return (int) $threshold;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }
}
