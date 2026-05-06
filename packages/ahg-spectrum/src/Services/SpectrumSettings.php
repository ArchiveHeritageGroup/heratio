<?php

namespace AhgSpectrum\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class SpectrumSettings
{
    protected string $table = 'spectrum';

    public function get(string $key, $default = null)
    {
        try {
            if (!Schema::hasTable($this->table)) {
                return $default;
            }
            $val = DB::table($this->table)->where('setting_key', $key)->value('setting_value');
            return $val === null ? $default : $val;
        } catch (\Throwable $e) {
            Log::warning('SpectrumSettings::get error: ' . $e->getMessage());
            return $default;
        }
    }

    public function isEnabled(): bool
    {
        $val = $this->get('spectrum_enabled', '0');
        return intval($val) === 1;
    }

    public function defaultCurrency(): string
    {
        return (string) $this->get('spectrum_default_currency', 'USD');
    }

    public function defaultLoanPeriodDays(): int
    {
        return intval($this->get('spectrum_loan_default_period', 30));
    }
}
