<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    public function rules(): array
    {
        return [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'service_problem_type_id' => ['required', 'exists:service_problem_types,id'],
            'technician_id' => ['nullable', 'exists:users,id'],
            'address_id' => ['required', 'exists:addresses,id'],
            'scheduled_at' => ['required', 'date'],
            'is_emergency' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
