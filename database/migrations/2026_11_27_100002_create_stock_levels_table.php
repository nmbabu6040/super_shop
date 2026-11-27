<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The quantity on hand of a variant (and batch) in a warehouse. It is a balance that the stock
     * service updates together with an immutable row in stock_movements.
     */
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            // Copied from the warehouse (NULL for a central warehouse) so branch isolation needs no join.
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->restrictOnDelete();
            // batch_id, or 0 when there is no batch: a UNIQUE index cannot compare NULLs.
            $table->unsignedBigInteger('batch_key')->default(0);
            $table->decimal('quantity', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'variant_id', 'batch_key']);
            $table->index(['variant_id', 'warehouse_id']);
            $table->index(['branch_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
