<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $availableLocales = ['en', 'es'];

        // Check if locale is stored in session
        if (session()->has('locale')) {
            $locale = session('locale');
        } else {
            // Get browser's preferred language
            $locale = $request->getPreferredLanguage($availableLocales);

            // Store in session for future requests
            session(['locale' => $locale]);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
