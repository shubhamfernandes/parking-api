<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\MaxStayDays;

class BookingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $today = now()->startOfDay()->toDateString();

        return [
            'from_date'   => ['bail', 'required', 'date', 'after_or_equal:'.$today],
            'to_datetime' => ['bail', 'required', 'date', 'after:from_date', new MaxStayDays('from_date')],
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.after_or_equal' => 'The start date must be today or a future date.',
            'to_datetime.after'        => 'The end date/time must be after the start date.',
        ];
    }
}
