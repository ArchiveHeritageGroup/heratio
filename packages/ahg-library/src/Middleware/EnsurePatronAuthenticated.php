<?php

/**
 * EnsurePatronAuthenticated - middleware for patron self-service portal
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLibrary\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class EnsurePatronAuthenticated
{
    /**
     * Handle an incoming request.
     * Redirects to the patron login route when the session has no patron_id.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Session::get('patron_id')) {
            return redirect()
                ->route('opac.patron.login')
                ->with('error', 'Please log in to access your library account.');
        }

        return $next($request);
    }
}
