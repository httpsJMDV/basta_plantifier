<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:80', 'email' => 'required|email|max:254|unique:users,email', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]];
    }
}
