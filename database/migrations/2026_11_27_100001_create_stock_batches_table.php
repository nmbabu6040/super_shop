<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A batch (lot) of a variant, identified by its number and usually carrying an expiry date.
     * Batches are not tied to a place: where the stock of a batch sits is in stock_levels.
     */
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('batch_number', 60);
            $table->date('expiry_date')->nullable();
            $table->date('manufactured_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['variant_id', 'batch_number']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
