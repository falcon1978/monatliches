<?php

namespace App\Http\Requests;

use App\Models\Entry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonthEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Entry::class) ?? false;
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $incomeSource = $this->input('income_source');
        $statusOptions = $type === 'income'
            ? ['open', 'partial', 'paid']
            : ['open', 'paid'];
        $requiresAccount = $type !== 'income' || $incomeSource !== 'manual';
        $accountRule = Rule::exists('accounts', 'id')
            ->where(fn ($query) => $query->where('user_id', $this->user()->id));

        if ($type === 'income' && $incomeSource === 'expected') {
            $accountRule = Rule::exists('accounts', 'id')
                ->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->whereIn('type', ['forecast', 'clearing']));
        }

        $amountRules = ['required', 'numeric'];
        if ($type !== 'income') {
            $amountRules[] = 'min:0.01';
        }

        return [
            'entry_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'required_if:type,expense'],
            'type' => ['required', Rule::in(['income', 'expense', 'fixcost'])],
            'income_source' => ['nullable', Rule::in(['expected', 'manual'])],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'amount' => $amountRules,
            'account_id' => [
                Rule::requiredIf($requiresAccount),
                'nullable',
                $accountRule,
            ],
            'status' => ['nullable', Rule::in($statusOptions)],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
