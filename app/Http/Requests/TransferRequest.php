<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'to_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
