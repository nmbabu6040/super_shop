<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Unknown notification types are rejected later by the service (only registered types count).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.email' => ['nullable', 'boolean'],
            'preferences.*.in_app' => ['nullable', 'boolean'],
        ];
    }
}
