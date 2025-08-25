<?php

namespace App\Http\Requests;

use App\Rules\MaxStayDays;
use Illuminate\Foundation\Http\FormRequest;

class BookingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $today = now()->startOfDay()->toDateString();

        return [
            'customer_name'  => ['bail', 'required', 'string', 'max:120'],
            'customer_email' => ['bail', 'required', 'string', 'email', 'max:120'],
            'vehicle_reg'    => ['bail', 'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9-\s]*$/i'],

            'from_date'      => ['bail', 'required', 'date', 'after_or_equal:' . $today],
            'to_datetime'    => ['bail', 'required', 'date', 'after:from_date', new MaxStayDays('from_date')],
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.after_or_equal' => 'The start date must be today or a future date.',
            'to_datetime.after'        => 'The end date/time must be after the start date.',
            'vehicle_reg.regex'        => 'The vehicle registration format is invalid.',
        ];
    }
}
