<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Support\Media\PublicImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BrandService
{
    private const LOGO_DIRECTORY = 'brands';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $logo = null): Brand
    {
        return DB::transaction(function () use ($data, $logo): Brand {
            $brand = new Brand();
            $brand->fill([
                ...Arr::except($data, ['slug', 'remove_logo']),
                'slug' => $this->resolveSlug($data),
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($logo !== null) {
                $brand->logo_path = PublicImageStore::store($logo, self::LOGO_DIRECTORY);
            }

            $brand->save();

            return $brand;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Brand $brand, array $data, ?UploadedFile $newLogo = null, bool $removeLogo = false): Brand
    {
        $previousLogoPath = $brand->logo_path;
        $logoIsChanging = $newLogo !== null || ($removeLogo && $previousLogoPath !== null);

        DB::transaction(function () use ($brand, $data, $newLogo, $removeLogo): void {
            $brand->fill(Arr::except($data, ['slug', 'remove_logo']));

            if (! empty($data['slug'])) {
                $brand->slug = $data['slug'];
            }

            if ($newLogo !== null) {
                $brand->logo_path = PublicImageStore::store($newLogo, self::LOGO_DIRECTORY);
            } elseif ($removeLogo) {
                $brand->logo_path = null;
            }

            $brand->save();
        });

        if ($logoIsChanging) {
            PublicImageStore::delete($previousLogoPath);
        }

        return $brand;
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }

    public function restore(Brand $brand): void
    {
        $brand->restore();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSlug(array $data): string
    {

        $baseSlug = ! empty($data['slug'])
            ? (string) $data['slug']
            : (Str::slug((string) ($data['name']['en'] ?? '')) ?: 'brand');


        $candidate = $baseSlug;
        $suffix = 2;

        while (Brand::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $baseSlug . '-' . $suffix++;
        }

        return $candidate;
    }
}
