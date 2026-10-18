<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return ['plant_record_id' => ['required', 'integer', Rule::exists('plant_records', 'id')->where('user_id', $this->user()->id)], 'title' => 'required|string|max:160', 'area' => 'nullable|string|max:160', 'description' => 'nullable|string|max:3000', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'visibility' => ['required', Rule::in(['private', 'public_approximate', 'public_exact'])], 'sensitive' => 'required|boolean', 'exact_confirmation' => 'accepted_if:visibility,public_exact', 'observed_on' => 'required|date|before_or_equal:'.now('Asia/Manila')->toDateString()];
    }
}
