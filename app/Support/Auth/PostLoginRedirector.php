<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Localization;

/**
 * Decides where a signed-in user lands: staff go to the admin area,
 * customers to their account page, anyone else to the storefront home.
 */
final class PostLoginRedirector
{
    public function routeNameFor(?User $user): string
    {
        if ($user === null) {
            return 'frontend.home';
        }

        if ($user->isCustomerOnly()) {
            return 'customer.account';
        }

        if ($user->getRoleNames()->isNotEmpty()) {
            return 'admin.dashboard';
        }

        return 'frontend.home';
    }

    public function urlFor(?User $user): string
    {
        $routeName = $this->routeNameFor($user);

        // Admin routes have no {locale} segment; passing one would add a stray query string.
        $parameters = str_starts_with($routeName, 'admin.')
            ? []
            : ['locale' => Localization::currentLocaleFor(request())];

        return route($routeName, $parameters);
    }
}
