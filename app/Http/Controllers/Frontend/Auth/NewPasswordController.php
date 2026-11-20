<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordResetService)
    {
    }

    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = $this->passwordResetService->reset([
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'password_confirmation' => (string) $request->input('password_confirmation'),
            'token' => $request->validated('token'),
        ]);

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('passwords.reset'));
        }

        // Unknown email and bad token give the same answer, so the form cannot be used to probe accounts.
        throw ValidationException::withMessages(['email' => __('passwords.token')]);
    }
}
