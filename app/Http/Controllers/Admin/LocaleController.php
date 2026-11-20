<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Localization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switches the admin interface language (the admin area has no /bn or /en URL prefix).
 * The choice is kept in the session and saved on the user's profile.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Localization::supportedLocaleCodes())],
        ]);

        $request->session()->put(Localization::sessionKey(), $validated['locale']);

        $user = $request->user();
        $user->preferred_locale = $validated['locale'];
        // Quietly: switching language is not an audit-worthy profile change.
        $user->saveQuietly();

        return redirect()->back();
    }
}
