<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('dimension', 24);
            $table->string('label', 191);
            $table->unsignedBigInteger('page_views')->default(0);
            $table->timestamps();
            $table->unique(['stat_date', 'dimension', 'label'], 'site_analytics_daily_dimension_unique');
            $table->index(['stat_date', 'dimension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_analytics_daily');
    }
};
