<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const DEFAULT_RECORDS_PER_PAGE = 50;

    protected static array $runtimeCache = [];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$runtimeCache)) {
            return static::$runtimeCache[$key];
        }

        return static::$runtimeCache[$key] = static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );

        static::$runtimeCache[$key] = (string) $value;
    }

    public static function recordsPerPage(): int
    {
        $value = (int) static::get('records_per_page', static::DEFAULT_RECORDS_PER_PAGE);

        return in_array($value, [10, 25, 50, 100], true)
            ? $value
            : static::DEFAULT_RECORDS_PER_PAGE;
    }
}
