<?php

declare(strict_types=1);

use Corepine\Actions\CorepineActions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(CorepineActions::formatTableName('actions'), function (Blueprint $table): void {
            $table->id();

            $table->string('actionable_id', 36);
            $table->string('actionable_type', 120);

            $table->string('actor_id', 36);
            $table->string('actor_type', 120);

            $table->string('type', 32);
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['actionable_type', 'actionable_id', 'type'], 'actions_target_type_idx');
            $table->index(['actor_type', 'actor_id'], 'actions_actor_idx');

            $table->unique(
                ['actionable_type', 'actionable_id', 'actor_type', 'actor_id', 'type'],
                'actions_unique_actor_target_type'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CorepineActions::formatTableName('actions'));
    }
};

