<?php

namespace App\Rules;

use Closure;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxStayDays implements ValidationRule
{
    public function __construct(
        private readonly string $fromField = 'from_date',
        private readonly ?int $maxDays = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $from = request()->input($this->fromField);
        if (!$from || !$value) {
            return;
        }

        try {
            $start = CarbonImmutable::parse($from)->startOfDay();
            $end   = CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return;
        }

        $nights = $start->diffInDays($end->startOfDay());
        $max    = $this->maxDays ?? (int) config('parking.max_stay_days', 10);

        if ($nights > $max) {
            $fail("The stay may not exceed {$max} days.");
        }
    }
}
