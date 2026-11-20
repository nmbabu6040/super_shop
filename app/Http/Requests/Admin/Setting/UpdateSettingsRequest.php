<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Setting;

use App\Models\Setting;
use App\Support\Settings\SettingsSchema;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateSettings', Setting::class);
    }

    protected function prepareForValidation(): void
    {
        $normalizedInput = [];

        foreach (SettingsSchema::fields((string) $this->route('group')) as $key => $field) {
            if ($field['readonly']) {
                continue;
            }

            if ($field['input'] === 'checkbox') {
                $normalizedInput[$key] = $this->boolean($key);
            }

            if ($field['input'] === 'color' && is_string($this->input($key))) {
                $normalizedInput[$key] = strtolower(trim($this->input($key)));
            }
        }

        $this->merge($normalizedInput);
    }

    /**
     * Rules are generated from the schema: only declared, non-readonly settings can be written.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $canManageSecrets = $this->user()->hasAnyPermission(['settings.manage']);
        $rules = [];

        foreach (SettingsSchema::fields((string) $this->route('group')) as $key => $field) {
            if ($field['readonly']) {
                continue;
            }

            switch ($field['input']) {
                case 'file':
                    $rules[$key] = $field['rules'];
                    $rules['remove_' . $key] = ['nullable', 'boolean'];
                    break;
                case 'secret':
                    if ($canManageSecrets) {
                        $rules[$key] = $field['rules'];
                        $rules['clear_' . $key] = ['nullable', 'boolean'];
                    } else {
                        $rules[$key] = ['prohibited'];
                    }
                    break;
                case 'checkbox':
                    $rules[$key] = ['boolean'];
                    break;
                default:
                    $rules[$key] = $field['rules'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $group = (string) $this->route('group');
        $attributes = [];

        foreach (array_keys(SettingsSchema::fields($group)) as $key) {
            $attributes[$key] = __("settings.fields.{$group}.{$key}");
        }

        return $attributes;
    }
}
