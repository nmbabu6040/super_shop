<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploaded images on the public disk under a random file name
 * (the original name is never trusted).
 */
final class PublicImageStore
{
    public const DISK = 'public';

    public static function store(UploadedFile $file, string $directory): string
    {
        return (string) $file->store($directory, self::DISK);
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::disk(self::DISK)->url($path);
    }
}
