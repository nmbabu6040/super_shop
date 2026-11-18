<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('phone', 30)->nullable()->unique()->after('email');
            $table->string('avatar_path')->nullable();
            $table->string('gender', 20)->nullable();

            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->string('status', 30)->default('active')->index();
            $table->string('preferred_locale', 5)->nullable();
            // Head office / super administrators: may access every branch.
            $table->boolean('has_all_branch_access')->default(false);

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'username',
                'phone',
                'avatar_path',
                'gender',
                'address_line',
                'city',
                'district',
                'postal_code',
                'status',
                'preferred_locale',
                'has_all_branch_access',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
