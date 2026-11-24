<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_medicine_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('generic_name', 150);
            $table->string('manufacturer', 150)->nullable();
            $table->string('drug_category', 100)->nullable();
            // Drug registration number issued by the regulator.
            $table->string('registration_number', 60)->nullable();
            // Safe default: a medicine requires a prescription unless someone deliberately says otherwise.
            $table->boolean('requires_prescription')->default(true);
            $table->boolean('is_controlled')->default(false);
            $table->string('storage_conditions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_medicine_details');
    }
};
