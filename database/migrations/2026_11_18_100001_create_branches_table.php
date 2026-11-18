<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            // Unique even for soft-deleted rows, so a branch code is never reused.
            $table->string('code', 20)->unique();
            $table->boolean('is_head_office')->default(false);
            $table->string('status', 30)->default('active')->index();

            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            // Used by the storefront store locator.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->date('opening_date')->nullable();
            // Informational here; the accounting opening entry is posted in the accounting phase.
            $table->decimal('opening_balance', 14, 2)->default(0);
            // JSON such as {"sat": {"open": "09:00", "close": "21:00"}, ...}
            $table->json('working_hours')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('tax_registration_number', 50)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
