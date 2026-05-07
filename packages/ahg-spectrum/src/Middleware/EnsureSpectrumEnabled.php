<?php

namespace AhgSpectrum\Middleware;

use Closure;
use AhgSpectrum\Services\SpectrumSettings;
use Illuminate\Http\Request;

class EnsureSpectrumEnabled
{
    protected SpectrumSettings $settings;

    public function __construct(SpectrumSettings $settings)
    {
        $this->settings = $settings;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->settings->isEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
