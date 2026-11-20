<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\SettingType;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings\SettingsSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reads and writes the settings forms of the admin UI, driven by config/settings_schema.php.
 *
 * Secrets: never returned to the browser (only "has a value" is), a blank input keeps the
 * stored secret, and changing one requires the "settings.manage" permission.
 */
final class SettingsFormService
{
    private const FILE_DISK = 'public';

    private const FILE_DIRECTORY = 'branding';

    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @return array<string, array{value: mixed, has_secret: bool}>
     */
    public function valuesFor(string $group): array
    {
        $values = [];

        foreach (SettingsSchema::fields($group) as $key => $field) {
            if ($field['input'] === 'secret') {
                $values[$key] = [
                    'value' => null,
                    'has_secret' => Setting::query()
                        ->where('group', $group)
                        ->where('key', $key)
                        ->whereNotNull('value')
                        ->exists(),
                ];

                continue;
            }

            $values[$key] = [
                'value' => $this->settings->get($group, $key, $field['default']),
                'has_secret' => false,
            ];
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $validated  Validated form input.
     * @param  array<string, mixed>  $uploadedFiles  Uploaded files keyed by setting key.
     */
    public function update(User $actor, string $group, array $validated, array $uploadedFiles = []): void
    {
        $canManageSecrets = $actor->hasAnyPermission(['settings.manage']);
        $filesToDeleteAfterCommit = [];

        DB::transaction(function () use ($group, $validated, $uploadedFiles, $canManageSecrets, &$filesToDeleteAfterCommit): void {
            foreach (SettingsSchema::fields($group) as $key => $field) {
                if ($field['readonly']) {
                    continue;
                }

                $type = SettingType::from($field['type']);

                match ($field['input']) {
                    'file' => $this->updateFile($group, $key, $field, $validated, $uploadedFiles, $filesToDeleteAfterCommit),
                    'secret' => $canManageSecrets ? $this->updateSecret($group, $key, $validated) : null,
                    'checkbox' => $this->settings->set($group, $key, (bool) ($validated[$key] ?? false), SettingType::Boolean, false, $field['public']),
                    default => $this->updateValue($group, $key, $field, $type, $validated),
                };
            }
        });

        // Old files are removed only after the database change succeeded.
        foreach ($filesToDeleteAfterCommit as $path) {
            Storage::disk(self::FILE_DISK)->delete($path);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $validated
     */
    private function updateValue(string $group, string $key, array $field, SettingType $type, array $validated): void
    {
        if (! array_key_exists($key, $validated)) {
            return;
        }

        $value = $validated[$key];

        if ($value === '' || $value === null) {
            $value = null;
        } elseif ($type === SettingType::Integer) {
            $value = (int) $value;
        }

        $this->settings->set($group, $key, $value, $type, false, $field['public']);
    }

    /**
     * A blank secret input keeps the stored value; "clear_<key>" removes it.
     *
     * @param  array<string, mixed>  $validated
     */
    private function updateSecret(string $group, string $key, array $validated): void
    {
        if (! empty($validated['clear_' . $key])) {
            $this->settings->set($group, $key, null, SettingType::String, true, false);

            return;
        }

        $newSecret = $validated[$key] ?? null;

        if (is_string($newSecret) && $newSecret !== '') {
            $this->settings->set($group, $key, $newSecret, SettingType::String, true, false);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $uploadedFiles
     * @param  list<string>  $filesToDeleteAfterCommit
     */
    private function updateFile(
        string $group,
        string $key,
        array $field,
        array $validated,
        array $uploadedFiles,
        array &$filesToDeleteAfterCommit
    ): void {
        $newFile = $uploadedFiles[$key] ?? null;
        $removeRequested = ! empty($validated['remove_' . $key]);
        $currentPath = $this->settings->get($group, $key);

        if ($newFile instanceof UploadedFile) {
            // store() generates a random file name; the original name is never trusted.
            $newPath = (string) $newFile->store(self::FILE_DIRECTORY, self::FILE_DISK);
            $this->settings->set($group, $key, $newPath, SettingType::String, false, $field['public']);

            if (is_string($currentPath) && $currentPath !== '') {
                $filesToDeleteAfterCommit[] = $currentPath;
            }

            return;
        }

        if ($removeRequested && is_string($currentPath) && $currentPath !== '') {
            $this->settings->set($group, $key, null, SettingType::String, false, $field['public']);
            $filesToDeleteAfterCommit[] = $currentPath;
        }
    }
}
