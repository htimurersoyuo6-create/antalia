<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('franchise_groups')) {
            Schema::create('franchise_groups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('slug', 180)->unique();
                $table->string('brand_color', 16)->nullable();
                $table->decimal('royalty_rate', 5, 2)->default(5.00);
                $table->decimal('marketing_fee_rate', 5, 2)->default(1.00);
                $table->char('currency', 3)->default('TRY');
                $table->string('status', 16)->default('active');
                $table->text('terms')->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'status']);
            });
        }

        if (! Schema::hasTable('franchise_units')) {
            Schema::create('franchise_units', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('franchise_group_id')->constrained('franchise_groups')->cascadeOnDelete();
                $table->foreignId('hotel_id')->unique()->constrained('hotels')->cascadeOnDelete();
                $table->decimal('royalty_rate_override', 5, 2)->nullable();
                $table->date('joined_at')->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();
                $table->index(['franchise_group_id', 'status']);
            });
        }

        if (! Schema::hasTable('franchise_royalty_statements')) {
            Schema::create('franchise_royalty_statements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('franchise_group_id')->constrained('franchise_groups')->cascadeOnDelete();
                $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
                $table->char('period', 7);
                $table->decimal('gross_revenue', 12, 2)->default(0);
                $table->decimal('royalty_rate', 5, 2)->default(0);
                $table->decimal('royalty_amount', 12, 2)->default(0);
                $table->decimal('marketing_fee', 12, 2)->default(0);
                $table->decimal('total_due', 12, 2)->default(0);
                $table->string('status', 16)->default('draft');
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['franchise_group_id', 'hotel_id', 'period']);
                $table->index(['period', 'status']);
            });
        }

        if (! Schema::hasTable('franchise_audits')) {
            Schema::create('franchise_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('franchise_group_id')->constrained('franchise_groups')->cascadeOnDelete();
                $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
                $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('audit_date');
                $table->unsignedTinyInteger('score')->default(0);
                $table->json('checklist')->nullable();
                $table->string('status', 16)->default('completed');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['franchise_group_id', 'hotel_id', 'audit_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_audits');
        Schema::dropIfExists('franchise_royalty_statements');
        Schema::dropIfExists('franchise_units');
        Schema::dropIfExists('franchise_groups');
    }
};
