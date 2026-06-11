<?php

namespace App\Livewire\Concerns;

trait ClampsMoney
{
    /**
     * Normalize a money input: null for blank/non-numeric, otherwise rounded to
     * cents and held within the decimal(8,2) column range so a typo can't 500.
     * Shared by the budget tracker and the season builder so both treat input
     * the same way.
     */
    protected function clampMoney(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return min((float) config('fencing.max_money'), max(0, round((float) $value, 2)));
    }
}
