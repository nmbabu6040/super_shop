<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('adjustment_number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            // Copied from the warehouse (NULL for a central warehouse).
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->date('adjustment_date')->index();
            $table->string('reason', 30);
            $table->string('status', 15)->index();
            $table->string('notes', 500)->nullable();
            // Net value of the posted adjustment (gains minus losses); NULL while it is pending.
            $table->decimal('total_value', 16, 2)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'adjustment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
