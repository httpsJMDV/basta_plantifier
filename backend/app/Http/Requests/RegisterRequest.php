<?php

namespace App\Http\Requests;

use App\Rules\DisplayName;
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
        $this->merge(['email' => mb_strtolower(trim((string) $this->email)), 'name' => DisplayName::normalize((string) $this->name)]);
    }

    public function rules(): array
    {
        return ['name' => DisplayName::rules(), 'email' => 'required|email|max:254|unique:users,email', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]];
    }
}
