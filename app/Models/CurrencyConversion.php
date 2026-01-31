<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CurrencyConversion extends Model
{
    use HasFactory;

    protected $table = 'currency_conversions';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'effective_date',
        'expiry_date',
        'is_active',
        'source',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the active conversion rate for a currency pair on a given date.
     * 
     * If direct rate is not found, tries to find reverse rate and calculates inverse.
     * 
     * @param string $fromCurrency Source currency code (e.g., 'LKR', 'USD')
     * @param string $toCurrency Target currency code (default: 'INR')
     * @param \DateTime|null $date Date to get the rate for (default: today)
     * @return float|null Conversion rate, or null if not found
     */
    public static function getRate(string $fromCurrency, string $toCurrency = 'INR', ?\DateTime $date = null): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $date = $date ?? now();
        
        // Try to find direct rate (fromCurrency → toCurrency)
        $rate = static::query()
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('is_active', true)
            ->where('effective_date', '<=', $date->format('Y-m-d'))
            ->where(function ($q) use ($date) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $date->format('Y-m-d'));
            })
            ->orderByDesc('effective_date')
            ->value('rate');

        if ($rate) {
            return (float) $rate;
        }

        // If direct rate not found, try reverse rate (toCurrency → fromCurrency) and calculate inverse
        $reverseRate = static::query()
            ->where('from_currency', $toCurrency)
            ->where('to_currency', $fromCurrency)
            ->where('is_active', true)
            ->where('effective_date', '<=', $date->format('Y-m-d'))
            ->where(function ($q) use ($date) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $date->format('Y-m-d'));
            })
            ->orderByDesc('effective_date')
            ->value('rate');

        if ($reverseRate) {
            // Calculate inverse: if 1 toCurrency = reverseRate fromCurrency, then 1 fromCurrency = 1/reverseRate toCurrency
            return 1.0 / (float) $reverseRate;
        }

        return null;
    }

    /**
     * Scope: Get active rates for a currency.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Get rates for a specific date.
     */
    public function scopeForDate(Builder $query, \DateTime $date): Builder
    {
        return $query->where('effective_date', '<=', $date->format('Y-m-d'))
            ->where(function ($q) use ($date) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $date->format('Y-m-d'));
            });
    }

    /**
     * Scope: Get rates for a currency pair.
     */
    public function scopeForCurrencyPair(Builder $query, string $fromCurrency, string $toCurrency): Builder
    {
        return $query->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency);
    }
}
