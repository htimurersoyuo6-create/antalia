<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('page_layout_edits', function (Blueprint $table): void {
            $table->id();
            $table->string('page_path', 255);
            $table->char('container_key', 64)->unique();
            $table->string('container_selector', 4000);
            $table->longText('order_json');
            $table->unsignedBigInteger('edited_by')->nullable()->index();
            $table->timestamps();
            $table->index('page_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_layout_edits');
    }
};
