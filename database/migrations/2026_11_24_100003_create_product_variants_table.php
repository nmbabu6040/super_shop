<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Every product has at least one variant: a simple product has exactly one (its default variant).
     * SKU, prices, barcodes and (later) stock all belong to the variant.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Unique even for deleted variants, so a SKU is never reused.
            $table->string('sku', 60)->unique();
            // Attributes of a variant such as {"size": "500g"} (used when extra variants arrive).
            $table->json('options')->nullable();
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            // Maximum retail price (mandatory printing on medicines); selling price can never exceed it.
            $table->decimal('mrp', 14, 2)->nullable();

            // Product id when this is the product's default variant, otherwise NULL. UNIQUE = one default per product.
            $table->foreignId('default_for_product_id')
                ->nullable()
                ->unique()
                ->constrained('products')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
