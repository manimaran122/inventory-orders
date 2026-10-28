<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Normalise an email for storage and lookup.
     *
     * Emails are matched case-insensitively ("Bob@Example.com" is the same
     * customer as "bob@example.com"), so both the unique constraint and the
     * "reuse existing customer" logic operate on the normalised form.
     */
    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeWithEmail(Builder $query, string $email): void
    {
        $query->where('email', self::normaliseEmail($email));
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $escaped = addcslashes(trim($term), '%_'.chr(92));

        $query->where(function (Builder $q) use ($escaped): void {
            $q->where('name', 'like', "%{$escaped}%")
                ->orWhere('email', 'like', "%{$escaped}%");
        });
    }
}
