<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'entry_id' => [
                'required',
                Rule::exists('entries', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'target_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('type', 'ist')),
            ],
        ];
    }
}
