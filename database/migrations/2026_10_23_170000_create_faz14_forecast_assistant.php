<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('demand_forecasts')) {
            Schema::create('demand_forecasts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
                $table->date('forecast_date');
                $table->decimal('occupancy_rate', 5, 2)->default(0);
                $table->decimal('booking_pace', 6, 2)->default(0);
                $table->decimal('season_index', 5, 2)->default(1);
                $table->decimal('suggested_multiplier', 5, 2)->default(1);
                $table->decimal('confidence', 4, 2)->default(0);
                $table->string('model_version', 20)->default('v1');
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
                $table->unique(['hotel_id', 'forecast_date']);
                $table->index(['forecast_date', 'suggested_multiplier']);
            });
        }

        if (! Schema::hasTable('assistant_faqs')) {
            Schema::create('assistant_faqs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('question', 255);
                $table->text('answer');
                $table->json('keywords')->nullable();
                $table->string('category', 60)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['hotel_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('assistant_interactions')) {
            Schema::create('assistant_interactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('channel', 16)->default('web');
                $table->text('question');
                $table->text('answer')->nullable();
                $table->unsignedBigInteger('matched_faq_id')->nullable();
                $table->decimal('confidence', 4, 2)->default(0);
                $table->boolean('handoff')->default(false);
                $table->timestamps();
                $table->index(['hotel_id', 'created_at']);
                $table->index('handoff');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_interactions');
        Schema::dropIfExists('assistant_faqs');
        Schema::dropIfExists('demand_forecasts');
    }
};
