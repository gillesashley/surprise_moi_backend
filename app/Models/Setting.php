<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return static::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'type' => $type,
                'description' => $description,
            ]
        );

        Cache::forget("setting:{$key}");
    }

    /**
     * Cast value based on type.
     */
    protected static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? (float) $value : 0,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Clear the cache when a setting is saved.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (Setting $setting) {
            Cache::forget("setting:{$setting->key}");
        });

        static::deleted(function (Setting $setting) {
            Cache::forget("setting:{$setting->key}");
        });
    }
}
