<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The stock ledger: one immutable row for every change of a stock level.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->restrictOnDelete();
            $table->string('movement_type', 30);
            // Signed: positive adds stock, negative removes it.
            $table->decimal('quantity', 14, 4);
            // Cost per base unit used for this movement (the average cost for outgoing movements).
            $table->decimal('unit_cost', 14, 4)->default(0);
            // Signed value of the movement: quantity x unit cost, rounded to 2 decimals.
            $table->decimal('total_cost', 16, 2)->default(0);
            // Quantity of this stock level right after the movement.
            $table->decimal('balance_after', 14, 4);
            // The document behind the movement (adjustment, sale, transfer...).
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['variant_id', 'warehouse_id', 'id']);
            $table->index(['warehouse_id', 'occurred_at']);
            $table->index(['branch_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
