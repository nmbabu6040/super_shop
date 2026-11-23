<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

return new class () extends Migration {
    /**
     * Tags every audit entry with the branch the actor was working in, so branch-bound
     * staff can be limited to their own branches. Old rows stay NULL (visible to head office only).
     */
    public function up(): void
    {
        Schema::table((new Activity())->getTable(), function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table((new Activity())->getTable(), function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'created_at']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
