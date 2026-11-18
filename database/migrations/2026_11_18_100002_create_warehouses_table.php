<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            // NULL means a central (head office level) warehouse that belongs to no single branch.
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('name', 190);
            $table->string('code', 30)->unique();
            $table->string('type', 30);
            // Only sellable warehouses can be sold from (POS / online) and shown as available stock.
            $table->boolean('is_sellable')->default(false);
            $table->boolean('is_active')->default(true);

            /*
             * Holds the branch id when this warehouse is that branch's default shop-floor
             * warehouse, otherwise NULL. A UNIQUE index lets MySQL guarantee "at most one
             * default warehouse per branch" (NULLs are allowed many times).
             */
            $table->foreignId('default_for_branch_id')
                ->nullable()
                ->unique()
                ->constrained('branches')
                ->restrictOnDelete();

            $table->string('address_line')->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
