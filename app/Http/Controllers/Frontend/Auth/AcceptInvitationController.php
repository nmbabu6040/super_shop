<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\Auth\AcceptInvitationRequest;
use App\Services\Users\StaffInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AcceptInvitationController extends Controller
{
    public function __construct(private readonly StaffInvitationService $invitationService)
    {
    }

    public function create(string $token): View
    {
        $invitation = $this->invitationService->findValid($token);

        return view('auth.accept-invitation', [
            'token' => $token,
            'invitedUser' => $invitation?->user,
        ]);
    }

    public function store(AcceptInvitationRequest $request): RedirectResponse
    {
        $this->invitationService->accept(
            $request->validated('token'),
            $request->validated('password'),
        );

        return redirect()->route('login')->with('status', __('invitation.accepted'));
    }
}
