<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\PostLoginRedirector;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function __construct(private readonly PostLoginRedirector $postLoginRedirector)
    {
    }

    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->to($this->postLoginRedirector->urlFor($user));
        }

        return view('auth.verify-email');
    }

    /**
     * Reached through the signed link in the email. EmailVerificationRequest checks that the
     * id and hash in the link belong to the signed-in user.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return redirect()
            ->to($this->postLoginRedirector->urlFor($request->user()))
            ->with('status', __('auth.email_verified'));
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->to($this->postLoginRedirector->urlFor($user));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', __('auth.verification_link_sent'));
    }
}
