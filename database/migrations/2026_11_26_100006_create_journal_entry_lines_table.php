<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            // The branch this line belongs to (reporting dimension); defaults to the entry's branch.
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo', 255)->nullable();
            $table->unsignedSmallInteger('line_number');
            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['branch_id', 'account_id']);
        });

        // The database itself refuses a line that is negative or has both a debit and a credit.
        DB::statement(
            'ALTER TABLE journal_entry_lines ADD CONSTRAINT journal_entry_lines_amounts_check '
                . 'CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
