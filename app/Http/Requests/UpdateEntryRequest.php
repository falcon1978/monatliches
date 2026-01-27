<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('entry')) ?? false;
    }

    public function rules(): array
    {
        $entry = $this->route('entry');
        $statusOptions = $entry && $entry->type === 'income'
            ? ['open', 'partial', 'paid']
            : ['open', 'paid'];
        $requiresAccount = ! $entry || $entry->type !== 'income' || $entry->income_source === 'expected';
        $accountRule = Rule::exists('accounts', 'id')
            ->where(fn ($query) => $query->where('user_id', $this->user()->id));

        if ($entry && $entry->type === 'income' && $entry->income_source === 'expected') {
            $accountRule = Rule::exists('accounts', 'id')
                ->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('type', 'forecast'));
        }

        $amountRules = ['required', 'numeric'];
        if (! $entry || $entry->type !== 'income') {
            $amountRules[] = 'min:0.01';
        }

        return [
            'entry_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', Rule::requiredIf($this->route('entry')?->type === 'expense')],
            'amount' => $amountRules,
            'account_id' => [
                Rule::requiredIf($requiresAccount),
                'nullable',
                $accountRule,
            ],
            'status' => ['required', Rule::in($statusOptions)],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
