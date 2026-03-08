<?php

declare(strict_types=1);

use Corepine\Actions\Models\Action;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((new Action())->getTable(), function (Blueprint $table): void {
            $table->id();

            $table->morphs('actionable');
            $table->morphs('actor');

            $table->string('type', 32);
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['actionable_type', 'actionable_id', 'type'], 'actions_target_type_idx');

            $table->unique(
                ['actionable_type', 'actionable_id', 'actor_type', 'actor_id', 'type'],
                'actions_unique_actor_target_type'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((new Action())->getTable());
    }
};
