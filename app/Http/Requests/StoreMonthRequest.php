<?php

namespace App\Http\Requests;

use App\Models\Month;
use Illuminate\Foundation\Http\FormRequest;

class StoreMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Month::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'daily_living_cost' => ['required', 'numeric', 'min:0'],
            'source_month_id' => ['nullable', 'integer'],
            'import_templates' => ['nullable', 'boolean'],
        ];
    }
}
