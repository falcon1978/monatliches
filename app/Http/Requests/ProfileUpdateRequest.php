<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accentPresets = User::accentPresets();
        $currentAccent = $this->user()?->accent_color;
        if ($currentAccent && ! in_array($currentAccent, $accentPresets, true)) {
            $accentPresets[] = $currentAccent;
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'employment_type' => ['required', Rule::in(['employed', 'self_employed'])],
            'accent_color' => [
                'nullable',
                'regex:/^#[0-9a-fA-F]{6}$/',
                Rule::in($accentPresets),
            ],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'profile_photo_cropped' => ['nullable', 'string'],
        ];
    }
}
