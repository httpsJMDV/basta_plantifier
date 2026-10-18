<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return ['common_name' => 'required|string|max:160', 'scientific_name' => 'nullable|string|max:200', 'family' => 'nullable|string|max:160', 'description' => 'nullable|string|max:12000', 'care' => 'nullable|string|max:8000', 'safety' => 'nullable|string|max:8000', 'relationship' => ['required', Rule::in(['growing', 'discovered', 'researching', 'wishlist'])], 'favorite' => 'sometimes|boolean', 'archived' => 'sometimes|boolean', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000'];
    }
}
