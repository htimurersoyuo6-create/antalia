<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $t) {
            $t->string('cover_path')->nullable()->after('phone');
            $t->text('policies')->nullable()->after('description');
        });
        Schema::create('pets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->enum('species', ['dog', 'cat', 'other']);
            $t->string('breed', 80)->nullable();
            $t->enum('size', ['small', 'medium', 'large'])->default('medium');
            $t->unsignedSmallInteger('birth_year')->nullable();
            $t->boolean('vaccinated')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->string('code', 12)->unique();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('hotel_id')->constrained();
            $t->foreignId('room_type_id')->constrained();
            $t->foreignId('pet_id')->constrained();
            $t->date('check_in');
            $t->date('check_out');
            $t->unsignedSmallInteger('nights');
            $t->decimal('total_price', 10, 2);
            $t->enum('status', ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'])->default('pending');
            $t->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $t->string('payment_method', 30)->default('pay_at_hotel');
            $t->text('customer_note')->nullable();
            $t->text('hotel_note')->nullable();
            $t->timestamps();
            $t->index(['room_type_id', 'check_in', 'check_out']);
            $t->index(['hotel_id', 'status']);
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('rating');
            $t->text('comment')->nullable();
            $t->boolean('published')->default(true);
            $t->timestamps();
        });
        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id(); $t->string('name', 120); $t->string('email', 160);
            $t->string('phone', 30)->nullable(); $t->text('message'); $t->timestamps();
        });
        Schema::create('posts', function (Blueprint $t) {
            $t->id(); $t->string('title', 200); $t->string('slug', 220)->unique();
            $t->string('excerpt', 300)->nullable(); $t->longText('body');
            $t->timestamp('published_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['posts', 'contact_messages', 'reviews', 'reservations', 'pets'] as $tbl) Schema::dropIfExists($tbl);
        Schema::table('hotels', fn (Blueprint $t) => $t->dropColumn(['cover_path', 'policies']));
    }
};
