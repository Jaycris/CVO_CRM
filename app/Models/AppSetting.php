<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const DEFAULT_RECORDS_PER_PAGE = 50;
    public const DEFAULT_LEADS_SALES_RECORDS_PER_PAGE = 50;
    public const DEFAULT_COMMISSION_EXCHANGE_RATE = 56.00;
    public const DEFAULT_CARD_PAYMENT_HOLD_PERCENT = 15.00;
    public const HRIS_API_TOKEN_KEY = 'hris_api_token';
    public const HRIS_BASE_URL_KEY = 'hris_base_url';
    public const HRIS_CRM_LOOKUP_TOKEN_KEY = 'hris_crm_lookup_token';

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

        return static::validRecordsPerPage($value)
            ? $value
            : static::DEFAULT_RECORDS_PER_PAGE;
    }

    public static function leadsSalesRecordsPerPage(): int
    {
        $value = (int) static::get('leads_sales_records_per_page', static::DEFAULT_LEADS_SALES_RECORDS_PER_PAGE);

        return static::validRecordsPerPage($value)
            ? $value
            : static::DEFAULT_LEADS_SALES_RECORDS_PER_PAGE;
    }

    public static function commissionExchangeRate(): float
    {
        $value = (float) static::get('commission_exchange_rate', static::DEFAULT_COMMISSION_EXCHANGE_RATE);

        return $value > 0
            ? $value
            : static::DEFAULT_COMMISSION_EXCHANGE_RATE;
    }

    public static function cardPaymentHoldPercent(): float
    {
        $value = (float) static::get('card_payment_hold_percent', static::DEFAULT_CARD_PAYMENT_HOLD_PERCENT);

        return $value >= 0
            ? $value
            : static::DEFAULT_CARD_PAYMENT_HOLD_PERCENT;
    }

    public static function hrisApiToken(): string
    {
        return (string) static::get(static::HRIS_API_TOKEN_KEY, config('services.hris.token'));
    }

    public static function hrisBaseUrl(): string
    {
        return (string) static::get(static::HRIS_BASE_URL_KEY, config('services.hris.base_url'));
    }

    public static function hrisCrmLookupToken(): string
    {
        return (string) static::get(static::HRIS_CRM_LOOKUP_TOKEN_KEY, config('services.hris.crm_lookup_token'));
    }

    private static function validRecordsPerPage(int $value): bool
    {
        return in_array($value, [10, 25, 50, 100], true);
    }
}
