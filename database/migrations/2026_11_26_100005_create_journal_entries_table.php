<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Journal entries are never changed or deleted once posted: mistakes are corrected by a reversing entry.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_number', 40)->unique();
            $table->date('entry_date')->index();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            // NULL = head office level entry.
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('status', 15)->index();
            $table->string('description', 255);
            $table->decimal('total_amount', 14, 2);

            // Which business event created the entry (e.g. "sale" #42, event "completed").
            // The UNIQUE index makes posting the same event twice impossible.
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 50)->nullable();

            // The entry this one reverses. UNIQUE: an entry can be reversed only once.
            $table->foreignId('reverses_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'source_event']);
            $table->index(['branch_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
