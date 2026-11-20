<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Branch\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets a user choose which branch they are currently working in.
 * BranchContext refuses a branch the user has no access to (HTTP 403).
 */
class ActiveBranchController extends Controller
{
    public function __invoke(Request $request, BranchContext $branchContext): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
        ]);

        $branchContext->setActiveBranch($validated['branch_id'] ?? null);

        return redirect()->back();
    }
}
