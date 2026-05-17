<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class SchedulePostRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'scheduled_at' => 'required|date|after:now',
            'timezone'     => 'required|timezone',
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after' => 'The scheduled time must be in the future.',
        ];
    }
}
