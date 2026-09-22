<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a pgvector `vector(N)` column (stored/returned by Postgres as a
 * string like "[0.1,0.2,0.3]") to and from a plain PHP float[].
 *
 * @implements CastsAttributes<array<int, float>, array<int, float>>
 */
class Vector implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, float>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [];
        }

        return array_map('floatval', explode(',', trim((string) $value, '[]')));
    }

    /**
     * @param  array<int, float>  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return '[' . implode(',', array_map('floatval', $value)) . ']';
    }
}
