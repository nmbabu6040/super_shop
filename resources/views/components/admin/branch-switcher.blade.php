@if ($branches->count() > 1 || $canViewAllBranches)
    <form method="POST" action="{{ route('admin.active-branch.update') }}" class="flex items-center gap-2">
        @csrf
        <label for="active-branch" class="sr-only">{{ __('admin.topbar.branch') }}</label>
        <x-ui.icon name="map-pin" class="size-4 text-ink-muted" />
        <select id="active-branch" name="branch_id" x-on:change="$el.form.submit()"
            class="max-w-44 rounded-full border border-line bg-surface-raised py-1.5 ps-3 pe-8 text-sm">
            @if ($canViewAllBranches)
                <option value="" @selected($activeBranchId === null)>{{ __('admin.topbar.all_branches') }}</option>
            @endif
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected($activeBranchId === $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </form>
@elseif ($branches->count() === 1)
    <span class="inline-flex items-center gap-2 text-sm text-ink-muted">
        <x-ui.icon name="map-pin" class="size-4" />
        {{ $branches->first()->name }}
    </span>
@endif
