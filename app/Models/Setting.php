<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Raw settings row. Always read and write through App\Services\Settings\SettingsService,
 * which handles typing, encryption and caching.
 */
class Setting extends Model
{
    use LogsActivity;

    public const CACHE_KEY = 'settings.rows';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_encrypted',
        'is_public',
        'value_updated_at',
        'updated_by',
    ];

    /**
     * Never serialize the raw (possibly encrypted) value by accident.
     */
    protected $hidden = ['value'];

    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
            'is_encrypted' => 'boolean',
            'is_public' => 'boolean',
            'value_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Any change (even a direct model edit) invalidates the cached settings.
        static::saved(static fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(static fn () => Cache::forget(self::CACHE_KEY));
    }

    public function getActivitylogOptions(): LogOptions
    {
        /*
         * Secrets must never reach the audit log. For encrypted settings we log only
         * metadata plus value_updated_at, which changes on every write, so the log still
         * shows WHEN and BY WHOM a secret changed without storing the secret.
         */
        $loggedAttributes = $this->is_encrypted
            ? ['group', 'key', 'is_encrypted', 'is_public', 'value_updated_at']
            : ['group', 'key', 'type', 'value', 'is_encrypted', 'is_public', 'value_updated_at'];

        return LogOptions::defaults()
            ->useLogName('settings')
            ->logOnly($loggedAttributes)
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
