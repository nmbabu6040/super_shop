<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Rbac\RbacSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'shop:create-super-admin
        {--name= : Full name}
        {--email= : Email address}';

    protected $description = 'Create a Super Administrator. No default credentials are ever seeded; the password is asked interactively.';

    public function handle(RbacSyncService $rbacSyncService): int
    {
        $name = (string) ($this->option('name') ?? $this->ask('Full name'));
        $email = (string) ($this->option('email') ?? $this->ask('Email address'));
        // The password is never accepted as a command-line option (it would end up in shell history).
        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $errorMessage) {
                $this->error($errorMessage);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($rbacSyncService, $name, $email, $password): void {
            // Make sure the roles exist even on a brand-new installation.
            $rbacSyncService->sync();

            $superAdmin = new User();
            $superAdmin->fill(['name' => $name, 'email' => $email, 'password' => $password]);
            // Privileged fields are not mass assignable; they are set explicitly here.
            $superAdmin->forceFill([
                'status' => UserStatus::Active,
                'has_all_branch_access' => true,
                'email_verified_at' => now(),
            ])->save();

            $superAdmin->assignRole(RoleName::SuperAdmin->value);
        });

        $this->info("Super Administrator [{$email}] created.");

        return self::SUCCESS;
    }
}
