<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('vaccination_cards', function (Blueprint $t): void {
            $t->id(); $t->foreignId('pet_id')->constrained()->cascadeOnDelete(); $t->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete(); $t->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete(); $t->string('path',255); $t->string('original_name',191); $t->string('mime',32); $t->unsignedInteger('size'); $t->json('ocr_result')->nullable(); $t->json('extracted_vaccines')->nullable(); $t->date('expires_at')->nullable(); $t->boolean('alerts_sent')->default(false); $t->timestamps(); $t->index(['pet_id','expires_at']);
        });
        Schema::create('preauth_blocks', function (Blueprint $t): void {
            $t->id(); $t->foreignId('reservation_id')->constrained()->cascadeOnDelete(); $t->foreignId('hotel_id')->constrained()->cascadeOnDelete(); $t->string('provider',16)->default('iyzico'); $t->string('payment_id',64); $t->string('preauth_id',64); $t->unsignedInteger('amount_cents'); $t->string('currency',3)->default('TRY'); $t->string('status',16)->default('blocked'); $t->timestamp('blocked_at'); $t->timestamp('released_at')->nullable(); $t->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete(); $t->text('reason')->nullable(); $t->timestamps(); $t->unique(['payment_id']); $t->index(['reservation_id','status']);
        });
        Schema::create('pricing_rules', function (Blueprint $t): void {
            $t->id(); $t->foreignId('hotel_id')->constrained()->cascadeOnDelete(); $t->date('start_date'); $t->date('end_date'); $t->unsignedTinyInteger('min_nights')->default(1); $t->unsignedInteger('base_multiplier_percent')->default(100); $t->decimal('surcharge_percent',5,2)->default(0); $t->boolean('is_active')->default(true); $t->timestamps(); $t->index(['hotel_id','start_date','end_date','is_active']);
        });
        Schema::create('geofenced_alerts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('pet_id')->constrained()->cascadeOnDelete(); $t->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete(); $t->decimal('lat',10,7); $t->decimal('lng',10,7); $t->unsignedInteger('radius_m')->default(5000); $t->string('status',16)->default('open'); $t->timestamp('reported_at'); $t->json('notified_user_ids')->nullable(); $t->timestamps(); $t->index(['status','reported_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('geofenced_alerts'); Schema::dropIfExists('pricing_rules'); Schema::dropIfExists('preauth_blocks'); Schema::dropIfExists('vaccination_cards');
    }
};