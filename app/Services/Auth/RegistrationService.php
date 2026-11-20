<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegistrationService
{
    /**
     * Creates a storefront customer account. The role is fixed to "customer";
     * nothing from the request can choose a role, status or branch access.
     *
     * @param  array{name: string, email: string, phone?: string|null, password: string}  $validated
     */
    public function register(array $validated): User
    {
        $user = DB::transaction(function () use ($validated): User {
            $user = new User();
            $user->fill([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'phone' => PhoneNumber::normalize($validated['phone'] ?? null),
                'password' => $validated['password'],
                'preferred_locale' => app()->getLocale(),
            ]);
            $user->forceFill(['status' => UserStatus::Active])->save();

            $user->assignRole(RoleName::Customer->value);

            return $user;
        });

        // Dispatched after the transaction commits. Sends the verification email and lets later
        // phases (e.g. the CRM customer record) react to new registrations.
        event(new Registered($user));

        return $user;
    }
}
