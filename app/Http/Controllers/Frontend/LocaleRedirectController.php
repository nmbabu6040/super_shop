<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Support\Localization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends a visitor who opens "/" to the home page in the right language.
 */
class LocaleRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route('frontend.home', [
            'locale' => Localization::preferredLocaleFor($request),
        ]);
    }
}
