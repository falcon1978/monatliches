<?php

namespace App\Http\Requests;

use App\Models\RecurringTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RecurringTemplate::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['income', 'fixcost'])],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'remaining_amount' => ['nullable', 'numeric', 'min:0'],
            'ends_on' => ['nullable', 'date'],
            'months' => ['required', 'array', 'min:1'],
            'months.*' => ['integer', 'between:1,12'],
        ];
    }
}
