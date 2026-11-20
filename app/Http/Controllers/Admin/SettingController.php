<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\Settings\SettingsFormService;
use App\Support\Settings\SettingsSchema;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsFormService $settingsFormService)
    {
    }

    public function index(): RedirectResponse
    {
        Gate::authorize('viewAny', Setting::class);

        return redirect()->route('admin.settings.edit', ['group' => SettingsSchema::groupKeys()[0]]);
    }

    public function edit(Request $request, string $group): View
    {
        Gate::authorize('viewAny', Setting::class);

        $user = $request->user();

        return view('admin.settings.edit', [
            'group' => $group,
            'groups' => SettingsSchema::groupKeys(),
            'fields' => SettingsSchema::fields($group),
            'values' => $this->settingsFormService->valuesFor($group),
            'canUpdate' => $user->can('updateSettings', Setting::class),
            'canManageSecrets' => $user->hasAnyPermission(['settings.manage']),
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        $this->settingsFormService->update(
            $request->user(),
            $group,
            $request->validated(),
            $request->allFiles(),
        );

        return redirect()->route('admin.settings.edit', ['group' => $group])
            ->with('success', __('settings.saved'));
    }
}
