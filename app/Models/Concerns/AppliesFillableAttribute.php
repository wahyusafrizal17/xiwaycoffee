<?php

namespace App\Models\Concerns;

use ReflectionClass;

trait AppliesFillableAttribute
{
    public function initializeAppliesFillableAttribute(): void
    {
        foreach ((new ReflectionClass(static::class))->getAttributes() as $attribute) {
            if (! str_ends_with($attribute->getName(), '\\Fillable')) {
                continue;
            }

            $columns = $attribute->getArguments()[0] ?? [];
            if ($columns !== []) {
                $this->mergeFillable($columns);
            }

            return;
        }
    }
}
