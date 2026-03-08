<?php

declare(strict_types=1);

use Corepine\Actions\Facades\Actions;
use Corepine\Actions\Models\ActionCount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((new ActionCount())->getTable(), function (Blueprint $table): void {
            $table->id();

            $table->string('actionable_id', 36);
            $table->string('actionable_type', 120);

            $table->string('type', 32);
            $table->unsignedBigInteger('count')->default(0);

            $table->timestamps();

            $table->index(['actionable_type', 'actionable_id'], 'action_counts_target_idx');
            $table->index(['type'], 'action_counts_type_idx');

            $table->unique(
                ['actionable_type', 'actionable_id', 'type'],
                'action_counts_unique_target_type'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((new ActionCount())->getTable());
    }
};
