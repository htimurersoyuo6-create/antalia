<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['customer','hotel_owner','staff','admin'])->default('customer');
            $t->string('phone', 30)->nullable();
        });
        Schema::create('cities', function (Blueprint $t) {
            $t->id(); $t->string('name', 80); $t->string('slug', 90)->unique();
        });
        Schema::create('districts', function (Blueprint $t) {
            $t->id(); $t->foreignId('city_id')->constrained();
            $t->string('name', 80); $t->string('slug', 90);
            $t->unique(['city_id', 'slug']);
        });
        Schema::create('hotels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users');
            $t->string('name', 160); $t->string('slug', 190)->unique();
            $t->text('description')->nullable();
            $t->foreignId('city_id')->constrained();
            $t->foreignId('district_id')->nullable()->constrained();
            $t->string('address')->nullable();
            $t->decimal('lat', 9, 6)->nullable(); $t->decimal('lng', 9, 6)->nullable();
            $t->string('phone', 30)->nullable();
            $t->boolean('accepts_dogs')->default(true);
            $t->boolean('accepts_cats')->default(false);
            $t->boolean('has_daycare')->default(false);
            $t->string('license_no', 80)->nullable();
            $t->timestamp('license_verified_at')->nullable();
            $t->enum('status', ['pending','active','suspended'])->default('pending');
            $t->decimal('commission_rate', 5, 2)->nullable();
            $t->decimal('rating_avg', 3, 2)->default(0);
            $t->unsignedInteger('rating_count')->default(0);
            $t->timestamps();
            $t->index(['city_id', 'status']);
        });
        Schema::create('room_types', function (Blueprint $t) {
            $t->id(); $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->enum('pet_type', ['dog','cat','other']);
            $t->enum('size', ['small','medium','large','any'])->default('any');
            $t->unsignedInteger('capacity')->default(1);
            $t->unsignedInteger('units')->default(1);
            $t->decimal('base_price', 10, 2);
        });
        Schema::create('price_rules', function (Blueprint $t) {
            $t->id(); $t->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $t->date('date_from'); $t->date('date_to'); $t->decimal('price', 10, 2);
            $t->index(['room_type_id', 'date_from', 'date_to']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('price_rules'); Schema::dropIfExists('room_types');
        Schema::dropIfExists('hotels'); Schema::dropIfExists('districts'); Schema::dropIfExists('cities');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone']));
    }
};
