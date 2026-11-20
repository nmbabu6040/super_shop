<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Placeholder customer account page. The full customer dashboard (orders, addresses,
 * wishlist, loyalty) is built in the eCommerce phase.
 */
class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('customer.account', ['user' => $request->user()]);
    }
}
