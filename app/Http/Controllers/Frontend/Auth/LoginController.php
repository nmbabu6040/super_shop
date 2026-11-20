<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\Auth\LoginRequest;
use App\Services\Auth\AuthenticationService;
use App\Support\Auth\PostLoginRedirector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly PostLoginRedirector $postLoginRedirector,
    ) {
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $this->authenticationService->attempt(
            $request->validated('identifier'),
            $request->validated('password'),
            $request->boolean('remember'),
            $request,
        );

        return redirect()->intended($this->postLoginRedirector->urlFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authenticationService->logout($request);

        return redirect()->route('frontend.home');
    }
}
