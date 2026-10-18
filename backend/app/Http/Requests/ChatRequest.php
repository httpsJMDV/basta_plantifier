<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $photo = 'file|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000';

        return [
            'body' => 'nullable|required_without_all:image,images|string|max:4000',
            'image' => 'nullable|prohibits:images|'.$photo,
            'images' => 'nullable|array|min:1|max:3',
            'images.*' => 'required|'.$photo,
            'response_style' => 'sometimes|in:formal,friendly,simple,scientific',
            'conversation_id' => 'nullable|integer',
        ];
    }
}
