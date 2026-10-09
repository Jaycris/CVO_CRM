<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'email_signature_enabled' => ['nullable', 'boolean'],
            'email_signature_name' => ['nullable', 'string', 'max:255'],
            'email_signature_title' => ['nullable', 'string', 'max:255'],
            'email_signature_contact_number' => ['nullable', 'string', 'max:255'],
            'email_signature_html' => ['nullable', 'string', 'max:2000000'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email_signature_enabled' => $this->boolean('email_signature_enabled'),
        ]);
    }
}
