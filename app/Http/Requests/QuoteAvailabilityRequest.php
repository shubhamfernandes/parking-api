<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
     public function rules(): array
    {

        $today = now()->startOfDay();
        $horizonDays = (int) config('booking.quote_horizon_days', 365);
        $limit = $today->clone()->addDays($horizonDays)->endOfDay()->toDateTimeString();

        return [
            'from_date'   => ['required', 'date', 'after_or_equal:' . $today->toDateString()],
            'to_datetime' => ['required', 'date', 'after:from_date', 'before_or_equal:' . $limit],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from_date.after_or_equal' => 'The start date must be today or a future date.',
            'to_datetime.after' => 'The end date/time must be after the start date.',
            'to_datetime.before_or_equal' => 'The end date/time cannot be more than one year in the future.',
        ];
    }
}
