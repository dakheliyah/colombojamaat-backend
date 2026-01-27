<?php

namespace App\Http\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FmbClearanceClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $retryAttempts;

    public function __construct()
    {
        $this->baseUrl = config('services.fmb.clearance_url', '');
        $this->timeout = config('services.fmb.timeout', 5);
        $this->retryAttempts = config('services.fmb.retry_attempts', 1);
    }

    /**
     * Check clearance status for an ITS ID.
     * Returns true if cleared, false otherwise (including on errors).
     * Always live - no caching.
     *
     * @param string $itsId The ITS ID to check
     * @return bool True if cleared, false otherwise
     */
    public function checkClearance(string $itsId): bool
    {
        if (empty($this->baseUrl)) {
            Log::warning('FMB clearance URL not configured');
            return false;
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->retryAttempts) {
            try {
                $response = Http::timeout($this->timeout)
                    ->get($this->baseUrl, [
                        'its_id' => $itsId,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    // Assuming API returns a boolean or a structure with 'cleared' field
                    // Adjust based on actual API response format
                    if (is_bool($data)) {
                        return $data;
                    }
                    return $data['cleared'] ?? false;
                }

                Log::warning('FMB clearance API returned non-success status', [
                    'its_id' => $itsId,
                    'status' => $response->status(),
                ]);

                return false;
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt <= $this->retryAttempts) {
                    Log::info('FMB clearance API request failed, retrying', [
                        'its_id' => $itsId,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
        }

        // Log final failure
        Log::error('FMB clearance API request failed after retries', [
            'its_id' => $itsId,
            'error' => $lastException?->getMessage(),
        ]);

        return false;
    }

    /**
     * Check clearance with detailed response.
     * Returns array with clearance status and additional details.
     *
     * @param string $itsId The ITS ID to check
     * @return array ['cleared' => bool, 'details' => array|null]
     */
    public function checkClearanceWithDetails(string $itsId): array
    {
        if (empty($this->baseUrl)) {
            Log::warning('FMB clearance URL not configured');
            return ['cleared' => false, 'details' => null];
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->retryAttempts) {
            try {
                $response = Http::timeout($this->timeout)
                    ->get($this->baseUrl, [
                        'its_id' => $itsId,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $cleared = false;
                    if (is_bool($data)) {
                        $cleared = $data;
                    } else {
                        $cleared = $data['cleared'] ?? false;
                    }
                    return [
                        'cleared' => $cleared,
                        'details' => $data,
                    ];
                }

                Log::warning('FMB clearance API returned non-success status', [
                    'its_id' => $itsId,
                    'status' => $response->status(),
                ]);

                return ['cleared' => false, 'details' => null];
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt <= $this->retryAttempts) {
                    Log::info('FMB clearance API request failed, retrying', [
                        'its_id' => $itsId,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
        }

        // Log final failure
        Log::error('FMB clearance API request failed after retries', [
            'its_id' => $itsId,
            'error' => $lastException?->getMessage(),
        ]);

        return ['cleared' => false, 'details' => null];
    }
}
