<?php

declare(strict_types=1);

namespace App\View\Components\Admin;

use App\Models\Branch;
use App\Support\Branch\BranchContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

/**
 * The "working branch" selector in the admin top bar. Lists only branches the user may access.
 */
class BranchSwitcher extends Component
{
    /** @var Collection<int, Branch> */
    public Collection $branches;

    public ?int $activeBranchId;

    public bool $canViewAllBranches;

    public function __construct(BranchContext $branchContext)
    {
        $user = auth()->user();

        $this->branches = $user->accessibleBranchesQuery()->orderBy('name')->get(['id', 'name']);
        $this->activeBranchId = $branchContext->activeBranchId();
        $this->canViewAllBranches = (bool) $user->has_all_branch_access;
    }

    public function render(): View
    {
        return view('components.admin.branch-switcher');
    }
}
