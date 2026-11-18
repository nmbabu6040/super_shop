<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Storefront home page. In Phase 2 this only renders the design-foundation
 * page; real homepage sections arrive in the eCommerce phase.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('frontend.home');
    }
}
