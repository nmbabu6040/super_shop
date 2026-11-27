<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Weighted-average cost per base unit of a variant in a warehouse (all batches together).
     * Its row is also the lock that serialises every movement of that variant in that warehouse.
     */
    public function up(): void
    {
        Schema::create('stock_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('average_cost', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_costs');
    }
};
