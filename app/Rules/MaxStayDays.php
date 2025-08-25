<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

class MaxStayDays implements ValidationRule
{
    public function __construct(
        private readonly string $fromField = 'from_date',
        private readonly ?int $maxDays = null
    ) {}

    /**
     * Validate that the number of occupied days does not exceed the configured maximum.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $from = request()->input($this->fromField);

        // Skip validation if required fields are missing
        if (!$from || !$value) {
            return;
        }

        try {
            $start = CarbonImmutable::parse($from)->startOfDay();
            $end   = CarbonImmutable::parse($value);
        } catch (Throwable) {
            // If parsing fails, let other validators handle invalid dates
            return;
        }

        $nights = $start->diffInDays($end->startOfDay());
        $max    = $this->maxDays ?? (int) config('parking.max_stay_days', 10);

        if ($nights > $max) {
            $fail("The stay may not exceed {$max} days.");
        }
    }
}
