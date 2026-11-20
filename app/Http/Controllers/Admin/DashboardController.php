<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Branch\BranchContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Placeholder admin landing page. The real dashboard with live charts and the full
 * admin layout arrives in the admin-panel and reporting phases.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, BranchContext $branchContext): View
    {
        $user = $request->user();
        $activeBranchId = $branchContext->activeBranchId();

        return view('admin.dashboard', [
            'user' => $user,
            'roleLabels' => $user->getRoleNames()->map(
                static fn (string $roleName): string => RoleName::tryFrom($roleName)?->label() ?? $roleName
            ),
            'activeBranch' => $activeBranchId === null ? null : Branch::query()->find($activeBranchId),
        ]);
    }
}
