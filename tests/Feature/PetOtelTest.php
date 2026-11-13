<?php
namespace Tests\Feature;

use App\Models\{City, DailyReport, ForumReply, ForumTopic, GeofencedAlert, Hotel, HotelSubscription, Pet, PriceRule, Reservation, ReservationPaymentAttempt, RoomType, RoomUnit, Setting, SubscriptionPlan, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Models\PendingPhoneRegistration;
use App\Services\GeofencedAlertService;
use Tests\TestCase;

class PetOtelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::kaydet(['booking_requests_enabled' => '1']);
    }

    private function captchaPost(string $url, array $data, string $key)
    {
        $started = time() - 2;
        return $this->withSession(['captcha.'.$key => ['answer' => '42', 'issued' => $started]])
            ->post($url, $data + ['_captcha_answer' => '42', '_captcha_website' => '', '_captcha_started' => $started]);
    }

    private function mkUser(string $role = 'customer', ?string $email = null): User
    {
        $u = new User(['name' => 'Ali Veli', 'email' => $email ?? uniqid().'@t.com', 'password' => 'password123']);
        $u->role = $role; $u->save();
        return $u;
    }

    private function mkHotel(?User $owner = null, int $units = 1): array
    {
        $owner ??= $this->mkUser('hotel_owner');
        $city = City::firstOrCreate(['slug' => 'antalya'], ['name' => 'Antalya']);
        $d = $city->districts()->firstOrCreate(['slug' => 'kemer'], ['name' => 'Kemer']);
        $h = Hotel::create(['owner_id' => $owner->id, 'name' => 'Test Otel', 'slug' => 'test-otel', 'city_id' => $city->id,
            'district_id' => $d->id, 'status' => 'active', 'accepts_dogs' => true, 'accepts_cats' => true]);
        $room = RoomType::create(['hotel_id' => $h->id, 'name' => 'Standart', 'pet_type' => 'dog', 'size' => 'any', 'units' => $units, 'base_price' => 1000]);
        for ($i = 1; $i <= $units; $i++) RoomUnit::create(['hotel_id' => $h->id, 'room_type_id' => $room->id, 'name' => 'Oda '.$i, 'status' => 'available']);
        $this->activateSubscription($owner, $h);
        return [$h, $room, $owner];
    }

    private function activateSubscription(User $owner, ?Hotel $hotel = null): void
    {
        $plan = SubscriptionPlan::firstOrCreate(['name' => 'Feature Test Plan'], ['monthly_price' => 500, 'active' => true]);
        HotelSubscription::create([
            'hotel_id' => $hotel?->id, 'plan_id' => $plan->id, 'requested_by' => $owner->id,
            'billing_cycle' => 'monthly', 'amount' => 500, 'status' => 'active', 'payment_status' => 'paid',
            'payment_method' => 'bank_transfer', 'paid_at' => now(), 'starts_at' => now()->subMinute(), 'ends_at' => now()->addMonth(),
        ]);
    }

    private function mkPet(User $u, string $species = 'dog'): Pet
    {
        return $u->pets()->create(['name' => 'Karabaş', 'species' => $species, 'size' => 'medium', 'vaccinated' => true]);
    }

    public function test_public_pages_render(): void
    {
        [$h] = $this->mkHotel();
        foreach (['/', '/ara', '/kategori/pet-otelleri/antalya', '/kategori/pet-otelleri/antalya/kopek-otelleri', '/pet-otel/test-otel',
                  '/blog', '/iletisim', '/partner', '/giris', '/kayit', '/sitemap.xml', '/sayfa/gizlilik', '/sayfa/iptal-iade', '/sayfa/hakkimizda', '/sayfa/kullanim-kosullari'] as $url) {
            $this->followingRedirects()->get($url)->assertOk();
        }
        $this->get('/sayfa/yok')->assertNotFound();
        $this->get(route('sitemap.main'))->assertSee(route('hotels.show', $h), false);
    }

    public function test_sitemap_includes_public_community_pages_and_active_listings_only(): void
    {
        $user = $this->mkUser();
        $publishedTopic = ForumTopic::create([
            'user_id' => $user->id, 'category' => 'pet-bakimi',
            'title' => 'Sitemap forum konusu', 'body' => 'Kamuya açık konu.',
            'status' => 'active',
        ]);
        $pendingTopic = ForumTopic::create([
            'user_id' => $user->id, 'category' => 'pet-bakimi',
            'title' => 'Bekleyen forum konusu', 'body' => 'Henüz yayınlanmadı.',
            'status' => 'pending',
        ]);
        $schema = \Illuminate\Support\Facades\Schema::connection('adoption');
        $createdAdoptionTable = !$schema->hasTable('adoption_listings');
        if ($createdAdoptionTable) {
            $schema->create('adoption_listings', function ($table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('pet_name');
                $table->string('species');
                $table->string('city')->default('Antalya');
                $table->text('description');
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        try {
            $publishedListing = \App\Models\AdoptionListing::create([
                'user_id' => $user->id, 'pet_name' => 'Sitemap dostu', 'species' => 'dog',
                'description' => 'Yuva arıyor.', 'status' => 'active',
            ]);
            $pendingListing = \App\Models\AdoptionListing::create([
                'user_id' => $user->id, 'pet_name' => 'Bekleyen ilan', 'species' => 'cat',
                'description' => 'Onay bekliyor.', 'status' => 'pending',
            ]);

            \Illuminate\Support\Facades\Cache::forget('sitemap.forum.count');
            \Illuminate\Support\Facades\Cache::forget('sitemap.forum.1');

            $this->get('/sitemap.xml')
                ->assertOk()
                ->assertSee(route('sitemap.main'), false)
                ->assertSee(route('sitemap.forum', ['page' => 1]), false);
            $this->get(route('sitemap.main'))
                ->assertOk()
                ->assertSee(route('forum.index'), false)
                ->assertSee(route('adoption.index'), false)
                ->assertSee(route('adoption.show', $publishedListing), false)
                ->assertDontSee(route('adoption.show', $pendingListing), false);
            $this->get(route('sitemap.forum', ['page' => 1]))
                ->assertOk()
                ->assertSee(route('forum.show', $publishedTopic), false)
                ->assertDontSee(route('forum.show', $pendingTopic), false);
        } finally {
            if ($createdAdoptionTable) {
                $schema->drop('adoption_listings');
            }
        }
    }

    public function test_register_and_login(): void
    {
        $this->captchaPost('/kayit', ['name' => 'Ayşe', 'email' => 'a@b.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'kvkk' => 1], 'register')
            ->assertRedirect(route('pets.create'));
        $this->assertAuthenticated();
        $this->assertSame('customer', User::first()->role);
        $this->post('/cikis')->assertRedirect('/');
        $this->post('/giris', ['identifier' => 'a@b.com', 'password' => 'yanlis'])->assertSessionHasErrors('identifier');
        $this->post('/giris', ['identifier' => 'a@b.com', 'password' => 'password123'])->assertRedirect(route('reservations.index'));
    }

    public function test_role_cannot_be_self_assigned(): void
    {
        $this->captchaPost('/kayit', ['name' => 'X', 'email' => 'x@b.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'kvkk' => 1, 'role' => 'admin'], 'register');
        $this->assertSame('customer', User::first()->role);
    }

    public function test_booking_flow_price_rules_and_double_booking(): void
    {
        [$h, $room, $owner] = $this->mkHotel(units: 1);
        PriceRule::create(['room_type_id' => $room->id, 'date_from' => now()->addDays(10), 'date_to' => now()->addDays(10), 'price' => 2000]);
        $cust = $this->mkUser(); $pet = $this->mkPet($cust);
        $in = now()->addDays(9)->toDateString(); $out = now()->addDays(12)->toDateString(); // geceler: +9,+10,+11
        $body = ['room_type_id' => $room->id, 'pet_id' => $pet->id, 'check_in' => $in, 'check_out' => $out, 'terms' => 1];

        $this->post('/pet-otel/test-otel/rezervasyon', $body)->assertRedirect('/giris');
        $this->actingAs($cust)->post('/pet-otel/test-otel/rezervasyon', $body)->assertSessionHasNoErrors();
        $res = Reservation::first();
        $this->assertSame(3, $res->nights);
        $this->assertEquals(4000.00, (float) $res->total_price); // 1000 + 2000 + 1000
        $this->assertSame('pending', $res->status);
        $this->assertNotNull($res->room_unit_id, 'A pending reservation must hold a physical unit.');
        $this->assertSame(1, RoomUnit::whereKey($res->room_unit_id)->count());

        $other = $this->mkUser(); $pet2 = $this->mkPet($other);
        $this->actingAs($other)->post('/pet-otel/test-otel/rezervasyon', ['pet_id' => $pet2->id] + $body)->assertSessionHasErrors('check_in');
        $this->assertSame(1, Reservation::count());

        // Bitişik tarih (çıkış günü = başka giriş günü) çakışma sayılmaz
        $this->actingAs($other)->post('/pet-otel/test-otel/rezervasyon', ['pet_id' => $pet2->id, 'check_in' => $out, 'check_out' => now()->addDays(13)->toDateString()] + $body)->assertSessionHasNoErrors();
        $this->assertSame(2, Reservation::count());
    }

    public function test_booking_requests_fail_closed_by_default_and_for_unclaimed_hotels(): void
    {
        [$hotel, $room, $owner] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);
        $body = [
            'room_type_id' => $room->id,
            'pet_id' => $pet->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'terms' => 1,
        ];

        Setting::query()->where('skey', 'booking_requests_enabled')->delete();
        \Illuminate\Support\Facades\Cache::forget('site_ayarlari');
        $quoteUrl = route('reservations.quote', $hotel->slug).'?check_in='
            .now()->addDays(3)->toDateString().'&check_out='.now()->addDays(5)->toDateString();

        $this->get(route('hotels.show', $hotel->slug))
            ->assertOk()
            ->assertDontSee('id="rezervasyon-talebi"')
            ->assertDontSee('Rezervasyon talebi gönder');
        $this->actingAs($customer)->post(route('reservations.store', $hotel->slug), $body)->assertStatus(503);
        $this->getJson($quoteUrl)->assertStatus(503)->assertJsonPath('ok', false);
        $this->assertDatabaseCount('reservations', 0);

        Setting::kaydet(['booking_requests_enabled' => '1']);
        $owner->update(['email' => Hotel::YER_TUTUCU_SAHIP]);
        $this->actingAs($customer)->post(route('reservations.store', $hotel->slug), $body)->assertStatus(503);
        $this->getJson($quoteUrl)->assertStatus(503)->assertJsonPath('ok', false);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_booking_requires_server_side_acceptance_of_cancellation_terms(): void
    {
        [$hotel, $room] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);

        $this->actingAs($customer)->post(route('reservations.store', $hotel->slug), [
            'room_type_id' => $room->id,
            'pet_id' => $pet->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'terms' => '0',
        ])->assertSessionHasErrors('terms');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_rejecting_a_pending_reservation_releases_its_room_capacity(): void
    {
        [$hotel, $room, $owner] = $this->mkHotel(units: 1);
        $checkIn = now()->addDays(5)->toDateString();
        $checkOut = now()->addDays(7)->toDateString();
        $firstCustomer = $this->mkUser();
        $firstPet = $this->mkPet($firstCustomer);
        $booking = [
            'room_type_id' => $room->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'terms' => 1,
        ];

        $this->actingAs($firstCustomer)->post(route('reservations.store', $hotel->slug), $booking + [
            'pet_id' => $firstPet->id,
        ])->assertSessionHasNoErrors();
        $firstReservation = Reservation::latest('id')->firstOrFail();
        $this->assertSame('pending', $firstReservation->status);

        $secondCustomer = $this->mkUser();
        $secondPet = $this->mkPet($secondCustomer);
        $this->actingAs($secondCustomer)->post(route('reservations.store', $hotel->slug), $booking + [
            'pet_id' => $secondPet->id,
        ])->assertSessionHasErrors('check_in');

        $this->actingAs($owner)->patch("/panel/rezervasyonlar/{$firstReservation->id}", [
            'action' => 'reject',
        ])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $firstReservation->fresh()->status);

        $this->actingAs($secondCustomer)->post(route('reservations.store', $hotel->slug), $booking + [
            'pet_id' => $secondPet->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, Reservation::where('hotel_id', $hotel->id)->count());
        $this->assertSame('pending', Reservation::latest('id')->firstOrFail()->status);
    }

    public function test_admin_can_toggle_online_booking_requests(): void
    {
        $admin = $this->mkUser('admin');
        $url = route('admin.settings.save');
        $settings = ['site_name' => 'Pet Otel', 'call_recording_days' => 30];

        $this->actingAs($admin)->put($url, $settings + ['booking_requests_enabled' => '1'])->assertSessionHas('ok');
        $this->assertSame('1', Setting::al('booking_requests_enabled', '0'));

        $this->actingAs($admin)->put($url, $settings)->assertSessionHas('ok');
        $this->assertSame('0', Setting::al('booking_requests_enabled', '0'));
    }

    public function test_faqs_remain_visible_without_faqpage_schema(): void
    {
        [$hotel] = $this->mkHotel();

        $this->get('/')
            ->assertOk()
            ->assertSee('Antalya’da kaç pet otel var?')
            ->assertSee('Organization')
            ->assertSee('WebSite')
            ->assertDontSee('FAQPage');

        $this->get(route('hotels.show', $hotel->slug))
            ->assertOk()
            ->assertSee('BreadcrumbList')
            ->assertDontSee('LocalBusiness')
            ->assertDontSee('FAQPage');

        $hotel->update(['license_verified_at' => now(), 'address' => 'Test adresi']);
        $this->get(route('hotels.show', $hotel->slug))
            ->assertOk()
            ->assertSee('LocalBusiness')
            ->assertSee('BreadcrumbList')
            ->assertDontSee('FAQPage');
    }

    public function test_hotel_search_filters_room_price_capacity_and_declared_stay_type(): void
    {
        [$hotel, $room] = $this->mkHotel();
        $hotel->update(['amenities' => json_encode(['Bireysel konaklama'])]);
        $room->update(['capacity' => 2, 'base_price' => 1000]);

        $this->get('/ara?min_fiyat=1200')->assertDontSee('Test Otel');
        $this->get('/ara?min_fiyat=800&max_fiyat=1200&kapasite=2&olanak%5B%5D=bireysel')
            ->assertOk()->assertSee('Test Otel');
        $this->get('/ara?kapasite=3')->assertDontSee('Test Otel');
        $this->get('/ara?olanak%5B%5D=grup')->assertDontSee('Test Otel');
        $this->get('/ara?min_fiyat=1200&max_fiyat=800')
            ->assertOk()
            ->assertSee('Minimum fiyat, maksimum fiyattan yüksek olamaz.');
    }

    public function test_minimum_and_maximum_price_facets_are_not_indexed_as_category_pages(): void
    {
        $this->mkHotel();

        foreach (['min_fiyat=800', 'max_fiyat=1200'] as $filter) {
            $this->get(route('hotels.dogs').'?'.$filter)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex, follow">', false)
                ->assertSee('<link rel="canonical" href="'.route('hotels.dogs').'">', false);
        }
    }

    public function test_pending_reviews_are_not_exposed_on_public_hotel_pages(): void
    {
        [$hotel, $room] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);
        $reservation = Reservation::create([
            'code' => 'POTESTRV01', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $room->id, 'pet_id' => $pet->id,
            'check_in' => now()->addDays(5), 'check_out' => now()->addDays(7),
            'nights' => 2, 'total_price' => 2000, 'status' => 'completed',
        ]);
        \App\Models\Review::create([
            'reservation_id' => $reservation->id, 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'rating' => 5, 'title' => 'Yayında yorum', 'comment' => 'Yayımlanmış doğrulanmış yorum.',
            'status' => 'published', 'created_at' => now(),
        ]);
        \App\Models\Review::create([
            'reservation_id' => Reservation::create([
                'code' => 'POTESTRV02', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
                'room_type_id' => $room->id, 'pet_id' => $pet->id,
                'check_in' => now()->addDays(8), 'check_out' => now()->addDays(10),
                'nights' => 2, 'total_price' => 2000, 'status' => 'completed',
            ])->id, 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'rating' => 1, 'title' => 'Bekleyen yorum', 'comment' => 'Bu içerik henüz moderasyonda.',
            'status' => 'pending', 'created_at' => now(),
        ]);

        $this->get(route('hotels.show', $hotel->slug))
            ->assertOk()
            ->assertSee('Yayımlanmış doğrulanmış yorum.')
            ->assertDontSee('Bu içerik henüz moderasyonda.');
    }

    public function test_local_brand_cover_is_marked_as_representative(): void
    {
        [$hotel] = $this->mkHotel();
        $hotel->update(['slug' => 'belekpatievi']);
        $this->assertTrue($hotel->hasRepresentativeCover());
        $this->get('/')->assertOk()->assertSee('Temsili görsel')->assertSee('Temsili görsel · Tesis fotoğrafı paylaşılmadı');
    }

    public function test_booking_request_with_unavailable_mailer_does_not_show_success(): void
    {
        Config::set('mail.default', 'log');
        [$hotel, $room] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);

        $this->actingAs($customer)->post(route('reservations.store', $hotel->slug), [
            'room_type_id' => $room->id,
            'pet_id' => $pet->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'terms' => 1,
        ])->assertSessionHas('warning')->assertSessionMissing('ok');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_concurrent_inventory_is_assigned_and_visible_to_hotel_owner(): void
    {
        [$hotel, $room, $owner] = $this->mkHotel(units: 2);
        $checkIn = now()->addDays(5)->toDateString();
        $checkOut = now()->addDays(7)->toDateString();
        $unitIds = [];

        foreach (range(1, 2) as $i) {
            $customer = $this->mkUser();
            $pet = $this->mkPet($customer);
            $this->actingAs($customer)->post('/pet-otel/'.$hotel->slug.'/rezervasyon', [
                'room_type_id' => $room->id, 'pet_id' => $pet->id,
                'check_in' => $checkIn, 'check_out' => $checkOut, 'terms' => 1,
            ])->assertSessionHasNoErrors();
            $unitIds[] = Reservation::latest('id')->first()->room_unit_id;
        }

        $this->assertCount(2, array_unique($unitIds));
        $this->assertNotContains(null, $unitIds);

        $thirdCustomer = $this->mkUser();
        $thirdPet = $this->mkPet($thirdCustomer);
        $this->actingAs($thirdCustomer)->post('/pet-otel/'.$hotel->slug.'/rezervasyon', [
            'room_type_id' => $room->id, 'pet_id' => $thirdPet->id,
            'check_in' => $checkIn, 'check_out' => $checkOut, 'terms' => 1,
        ])->assertSessionHasErrors('check_in');
        $this->assertSame(2, Reservation::where('hotel_id', $hotel->id)->count());

        $this->actingAs($owner)->get('/panel/otel/'.$hotel->id)
            ->assertOk()->assertSee('Oda yönetimi ve doluluk')->assertSee('Gelecek rezervasyon');
    }

    public function test_approved_paid_reservation_extension_is_separately_payable(): void
    {
        [$hotel, $room, $owner] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);
        $checkIn = now()->addDays(3)->startOfDay();
        $checkOut = now()->addDays(5)->startOfDay();
        $reservation = Reservation::create([
            'code' => 'POEXT00001', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $room->id, 'room_unit_id' => RoomUnit::where('room_type_id', $room->id)->first()->id,
            'pet_id' => $pet->id, 'check_in' => $checkIn, 'check_out' => $checkOut,
            'nights' => 2, 'total_price' => 2000, 'status' => 'confirmed', 'payment_status' => 'paid',
        ]);

        $this->actingAs($customer)->post('/rezervasyonlarim/'.$reservation->id.'/uzatma', [
            'requested_check_out' => $checkOut->copy()->addDays(2)->toDateString(),
        ])->assertSessionHasNoErrors();
        $extension = $reservation->extensions()->firstOrFail();
        $this->actingAs($owner)->post('/panel/rezervasyonlar/'.$reservation->id.'/uzatmalar/'.$extension->id, [
            'action' => 'approve',
        ])->assertSessionHasNoErrors();

        $extension->refresh();
        $reservation->refresh();
        $this->assertSame('approved', $extension->status);
        $this->assertSame($checkOut->copy()->addDays(2)->toDateString(), $reservation->check_out->toDateString());
        $this->assertSame('paid', $reservation->payment_status, 'The extension must not alter the paid state of the base reservation.');
        $this->assertSame('pending', $extension->financialEntry->status);
        $this->assertEquals((float) $extension->quoted_total, (float) $extension->financialEntry->amount);

        Config::set('services.iyzico.environment', 'disabled');
        Config::set('services.iyzico.api_key', '');
        Config::set('services.iyzico.secret_key', '');
        $this->actingAs($customer)->get('/rezervasyonlarim/'.$reservation->id)
            ->assertOk()->assertSee('Uzatma bedelini güvenli öde')->assertSee('iyzico sandbox anahtarları yapılandırılmadı');
        $this->post('/rezervasyonlarim/'.$reservation->id.'/uzatma/'.$extension->id.'/odeme/iyzico', [])
            ->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('reservation_extension_payment_attempts', 0);
        $this->assertSame('pending', $extension->financialEntry->fresh()->status);
        $this->assertSame('paid', $reservation->fresh()->payment_status);
    }

    public function test_extension_approval_waits_for_an_active_base_card_payment(): void
    {
        [$hotel, $room, $owner] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);
        $checkIn = now()->addDays(4)->startOfDay();
        $checkOut = now()->addDays(6)->startOfDay();
        $reservation = Reservation::create([
            'code' => 'POEXT00002', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $room->id, 'room_unit_id' => RoomUnit::where('room_type_id', $room->id)->first()->id,
            'pet_id' => $pet->id, 'check_in' => $checkIn, 'check_out' => $checkOut,
            'nights' => 2, 'total_price' => 2000, 'status' => 'confirmed', 'payment_status' => 'unpaid',
        ]);
        ReservationPaymentAttempt::create([
            'reservation_id' => $reservation->id, 'user_id' => $customer->id,
            'conversation_id' => 'APO-' . strtoupper(\Illuminate\Support\Str::random(24)),
            'token' => 'sandbox-extension-lock-test', 'checkout_url' => 'https://sandbox-api.iyzipay.com/test',
            'amount' => 2000, 'currency' => 'TRY', 'status' => 'pending', 'attempt_count' => 1,
        ]);
        $extension = $reservation->extensions()->create([
            'requested_by' => $customer->id, 'current_check_out' => $checkOut->toDateString(),
            'requested_check_out' => $checkOut->copy()->addDay()->toDateString(), 'added_nights' => 1,
            'quoted_room_total' => 1000, 'quoted_discount_total' => 0, 'quoted_extras_total' => 0, 'quoted_total' => 1000,
        ]);

        $this->actingAs($owner)->post('/panel/rezervasyonlar/'.$reservation->id.'/uzatmalar/'.$extension->id, [
            'action' => 'approve',
        ])->assertSessionHasErrors('extension');
        $this->assertSame('pending', $extension->fresh()->status);
        $this->assertSame($checkOut->toDateString(), $reservation->fresh()->check_out->toDateString());
        $this->assertEquals(2000, (float) $reservation->fresh()->total_price);
    }

    public function test_booking_validation(): void
    {
        [$h, $room] = $this->mkHotel();
        $cust = $this->mkUser(); $cat = $this->mkPet($cust, 'cat');
        $base = ['room_type_id' => $room->id, 'pet_id' => $cat->id, 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(3)->toDateString(), 'terms' => 1];
        $this->actingAs($cust)->post('/pet-otel/test-otel/rezervasyon', $base)->assertSessionHasErrors('pet_id'); // köpek odası, kedi
        $dog = $this->mkPet($cust);
        $this->actingAs($cust)->post('/pet-otel/test-otel/rezervasyon', ['pet_id' => $dog->id, 'check_in' => now()->subDay()->toDateString()] + $base)->assertSessionHasErrors('check_in');
        $this->actingAs($cust)->post('/pet-otel/test-otel/rezervasyon', ['pet_id' => $dog->id, 'check_out' => now()->toDateString()] + $base)->assertSessionHasErrors('check_out');
        $stranger = $this->mkUser();
        $this->actingAs($stranger)->post('/pet-otel/test-otel/rezervasyon', ['pet_id' => $dog->id] + $base)->assertNotFound(); // başkasının hayvanı
    }

    public function test_cat_booking_is_not_rejected_by_dog_size_rules(): void
    {
        [$hotel] = $this->mkHotel();
        $catRoom = RoomType::create(['hotel_id' => $hotel->id, 'name' => 'Kedi Odası', 'pet_type' => 'cat', 'size' => 'small', 'units' => 1, 'base_price' => 900]);
        RoomUnit::create(['hotel_id' => $hotel->id, 'room_type_id' => $catRoom->id, 'name' => 'Kedi 1', 'status' => 'available']);
        $customer = $this->mkUser();
        $cat = $this->mkPet($customer, 'cat');
        $this->actingAs($customer)->post('/pet-otel/test-otel/rezervasyon', [
            'room_type_id' => $catRoom->id, 'pet_id' => $cat->id,
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(), 'terms' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['hotel_id' => $hotel->id, 'room_type_id' => $catRoom->id, 'pet_id' => $cat->id]);
    }

    public function test_owner_workflow_and_review(): void
    {
        [$h, $room, $owner] = $this->mkHotel();
        $cust = $this->mkUser(); $pet = $this->mkPet($cust);
        $res = Reservation::create(['code' => 'POTEST0001', 'user_id' => $cust->id, 'hotel_id' => $h->id, 'room_type_id' => $room->id, 'pet_id' => $pet->id,
            'check_in' => now()->subDays(3), 'check_out' => now()->subDay(), 'nights' => 2, 'total_price' => 2000]);

        $this->actingAs($cust)->patch("/panel/rezervasyonlar/{$res->id}", ['action' => 'confirm'])->assertForbidden();
        $stranger = $this->mkUser('hotel_owner');
        $this->activateSubscription($stranger);
        $this->actingAs($stranger)->patch("/panel/rezervasyonlar/{$res->id}", ['action' => 'confirm'])->assertForbidden();

        $this->actingAs($owner)->patch("/panel/rezervasyonlar/{$res->id}", ['action' => 'confirm']);
        $this->assertSame('confirmed', $res->fresh()->status);
        $this->actingAs($owner)->patch("/panel/rezervasyonlar/{$res->id}", ['action' => 'complete']);
        $this->assertSame('completed', $res->fresh()->status);
        $this->assertSame('unpaid', $res->fresh()->payment_status); // completion alone is not proof of payment

        $this->actingAs($cust)->post("/rezervasyonlarim/{$res->id}/yorum", ['rating' => 4, 'comment' => 'Güzeldi'])->assertSessionHasNoErrors();
        $this->assertEquals(4.0, (float) $h->fresh()->rating_avg);
        $this->assertSame(1, $h->fresh()->rating_count);
        $this->actingAs($cust)->post("/rezervasyonlarim/{$res->id}/yorum", ['rating' => 1])->assertSessionHasErrors('rating'); // ikinci yorum yok
        $this->followingRedirects()->get('/pet-otel/test-otel')->assertSee('Güzeldi');
    }

    public function test_reservation_privacy_and_cancel(): void
    {
        [$h, $room] = $this->mkHotel();
        $cust = $this->mkUser(); $pet = $this->mkPet($cust);
        $res = Reservation::create(['code' => 'POTEST0002', 'user_id' => $cust->id, 'hotel_id' => $h->id, 'room_type_id' => $room->id, 'pet_id' => $pet->id,
            'check_in' => now()->addDays(5), 'check_out' => now()->addDays(7), 'nights' => 2, 'total_price' => 2000]);
        $this->actingAs($this->mkUser())->get("/rezervasyonlarim/{$res->id}")->assertForbidden();
        $this->actingAs($cust)->get("/rezervasyonlarim/{$res->id}")->assertOk()->assertSee('POTEST0002');
        $this->actingAs($cust)->post("/rezervasyonlarim/{$res->id}/iptal");
        $this->assertSame('cancelled', $res->fresh()->status);
    }

    public function test_customer_cannot_cancel_reservation_during_card_checkout(): void
    {
        [$hotel, $room] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);
        $reservation = Reservation::create([
            'code' => 'POPAY00001', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $room->id, 'pet_id' => $pet->id,
            'check_in' => now()->addDays(5), 'check_out' => now()->addDays(7),
            'nights' => 2, 'total_price' => 2000, 'status' => 'confirmed',
        ]);
        ReservationPaymentAttempt::create([
            'reservation_id' => $reservation->id, 'user_id' => $customer->id,
            'conversation_id' => 'APO-' . strtoupper(\Illuminate\Support\Str::random(24)),
            'token' => 'sandbox-reservation-token',
            'checkout_url' => 'https://sandbox-api.iyzipay.com/test',
            'amount' => 2000, 'currency' => 'TRY', 'status' => 'pending', 'attempt_count' => 1,
        ]);

        $this->actingAs($customer)->post("/rezervasyonlarim/{$reservation->id}/iptal")
            ->assertRedirect()->assertSessionHasErrors('res');
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_search_filters_by_availability(): void
    {
        [$h, $room] = $this->mkHotel(units: 1);
        $cust = $this->mkUser(); $pet = $this->mkPet($cust);
        Reservation::create(['code' => 'POTEST0003', 'user_id' => $cust->id, 'hotel_id' => $h->id, 'room_type_id' => $room->id, 'pet_id' => $pet->id,
            'check_in' => now()->addDays(5), 'check_out' => now()->addDays(8), 'nights' => 3, 'total_price' => 3000, 'status' => 'confirmed']);
        $busy = '/ara?giris='.now()->addDays(6)->toDateString().'&cikis='.now()->addDays(7)->toDateString();
        $free = '/ara?giris='.now()->addDays(20)->toDateString().'&cikis='.now()->addDays(22)->toDateString();
        $this->get($busy)->assertDontSee('Test Otel');
        $this->get($free)->assertSee('Test Otel');
    }

    public function test_search_reports_when_only_one_availability_date_is_provided(): void
    {
        $this->mkHotel();

        $this->get('/ara?cikis='.now()->addDays(3)->toDateString())
            ->assertOk()
            ->assertSee('Müsaitlik aramak için giriş ve çıkış tarihlerini birlikte seçin.');
    }

    public function test_search_rejects_nonexistent_calendar_dates(): void
    {
        $this->mkHotel();

        $this->get('/ara?giris=2027-02-31&cikis=2027-03-04')
            ->assertOk()
            ->assertSee('Tarihler okunamadı. Hem giriş hem çıkış tarihini seçin.');
    }

    public function test_search_supports_room_price_review_and_verification_sorting(): void
    {
        [$lowest, $lowestRoom] = $this->mkHotel();
        $city = City::where('slug', 'antalya')->firstOrFail();
        $district = $city->districts()->where('slug', 'kemer')->firstOrFail();

        $middleOwner = $this->mkUser('hotel_owner');
        $middle = Hotel::create([
            'owner_id' => $middleOwner->id, 'name' => 'Orta Fiyat Otel', 'slug' => 'orta-fiyat-otel',
            'city_id' => $city->id, 'district_id' => $district->id, 'status' => 'active',
            'accepts_dogs' => true, 'accepts_cats' => true, 'rating_avg' => 4.1, 'rating_count' => 40,
        ]);
        RoomType::create([
            'hotel_id' => $middle->id, 'name' => 'Standart', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 1, 'base_price' => 800,
        ]);
        RoomType::create([
            'hotel_id' => $middle->id, 'name' => 'Aile', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 1, 'base_price' => 3000,
        ]);

        $highestRatingOwner = $this->mkUser('hotel_owner');
        $highestRating = Hotel::create([
            'owner_id' => $highestRatingOwner->id, 'name' => 'Yüksek Puan Otel', 'slug' => 'yuksek-puan-otel',
            'city_id' => $city->id, 'district_id' => $district->id, 'status' => 'active',
            'accepts_dogs' => true, 'accepts_cats' => true, 'rating_avg' => 4.8, 'rating_count' => 20,
            'license_verified_at' => now(),
        ]);
        RoomType::create([
            'hotel_id' => $highestRating->id, 'name' => 'Standart', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 1, 'base_price' => 700,
        ]);
        RoomType::create([
            'hotel_id' => $highestRating->id, 'name' => 'Aile', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 1, 'base_price' => 2000,
        ]);

        $lowest->update(['rating_avg' => 4.5, 'rating_count' => 25]);
        $lowestRoom->update(['base_price' => 500]);
        RoomType::create([
            'hotel_id' => $lowest->id, 'name' => 'Aile', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 1, 'base_price' => 1000,
        ]);

        $assertOrder = function (string $sort, array $expected) {
            $response = $this->get('/ara?sirala=' . $sort)->assertOk();
            $content = $response->getContent();
            $positions = array_map(
                fn (Hotel $hotel) => strpos($content, '>' . e($hotel->name) . '</a>'),
                $expected
            );
            $this->assertNotContains(false, $positions);
            $ordered = $positions;
            sort($ordered);
            $this->assertSame($ordered, $positions, 'Unexpected order for ' . $sort);
        };

        $assertOrder('ucuz', [$lowest, $highestRating, $middle]);
        $assertOrder('pahali', [$middle, $highestRating, $lowest]);
        $assertOrder('puan', [$highestRating, $lowest, $middle]);
        $assertOrder('yorum', [$middle, $lowest, $highestRating]);
        $assertOrder('dogrulanmis', [$highestRating, $middle, $lowest]);
    }

    public function test_available_room_ids_batches_inventory_queries_and_preserves_legacy_capacity(): void
    {
        [$hotel, $fullyBookedRoom] = $this->mkHotel(units: 1);
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);
        $checkIn = now()->addDays(6);
        $checkOut = now()->addDays(8);

        $partiallyBookedRoom = RoomType::create([
            'hotel_id' => $hotel->id, 'name' => 'İki Üniteli Oda', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 2, 'base_price' => 1200,
        ]);
        $partialUnits = collect(range(1, 2))->map(fn ($index) => RoomUnit::create([
            'hotel_id' => $hotel->id, 'room_type_id' => $partiallyBookedRoom->id,
            'name' => 'İki Üniteli Oda '.$index, 'status' => 'available',
        ]));
        $legacyBookedRoom = RoomType::create([
            'hotel_id' => $hotel->id, 'name' => 'Eski Kayıt Odası', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 1, 'base_price' => 900,
        ]);
        RoomUnit::create([
            'hotel_id' => $hotel->id, 'room_type_id' => $legacyBookedRoom->id,
            'name' => 'Eski Kayıt Odası', 'status' => 'available',
        ]);
        $availableRoom = RoomType::create([
            'hotel_id' => $hotel->id, 'name' => 'Boş Oda', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 1, 'base_price' => 1000,
        ]);
        RoomUnit::create([
            'hotel_id' => $hotel->id, 'room_type_id' => $availableRoom->id,
            'name' => 'Boş Oda', 'status' => 'available',
        ]);

        Reservation::create([
            'code' => 'POTESTAVA01', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $fullyBookedRoom->id, 'room_unit_id' => $fullyBookedRoom->inventoryUnits()->firstOrFail()->id,
            'pet_id' => $pet->id, 'check_in' => $checkIn, 'check_out' => $checkOut,
            'nights' => 2, 'total_price' => 2000, 'status' => 'confirmed',
        ]);
        Reservation::create([
            'code' => 'POTESTAVA02', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $partiallyBookedRoom->id, 'room_unit_id' => $partialUnits->first()->id,
            'pet_id' => $pet->id, 'check_in' => $checkIn, 'check_out' => $checkOut,
            'nights' => 2, 'total_price' => 2400, 'status' => 'confirmed',
        ]);
        Reservation::create([
            'code' => 'POTESTAVA03', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $legacyBookedRoom->id, 'pet_id' => $pet->id,
            'check_in' => $checkIn, 'check_out' => $checkOut,
            'nights' => 2, 'total_price' => 1800, 'status' => 'confirmed',
        ]);

        $rooms = RoomType::where('hotel_id', $hotel->id)->get();
        $connection = \Illuminate\Support\Facades\DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $availableIds = app(\App\Services\BookingService::class)->availableRoomIds($rooms, $checkIn, $checkOut);
            $queries = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        $this->assertEqualsCanonicalizing([$partiallyBookedRoom->id, $availableRoom->id], $availableIds);
        $this->assertCount(2, $queries);
    }

    public function test_care_dashboard_only_shows_reports_for_owned_hotels(): void
    {
        [$hotel, $room, $owner] = $this->mkHotel();
        $otherOwner = $this->mkUser('hotel_owner');
        $otherHotel = Hotel::create([
            'owner_id' => $otherOwner->id, 'name' => 'Other Hotel', 'slug' => 'other-test-otel',
            'city_id' => $hotel->city_id, 'district_id' => $hotel->district_id,
            'status' => 'active', 'accepts_dogs' => true, 'accepts_cats' => true,
        ]);
        $this->activateSubscription($otherOwner, $otherHotel);
        $otherRoom = RoomType::create([
            'hotel_id' => $otherHotel->id, 'name' => 'Standart', 'pet_type' => 'dog',
            'size' => 'any', 'units' => 1, 'base_price' => 1000,
        ]);
        $customer = $this->mkUser();
        $ownPet = $this->mkPet($customer);
        $otherPet = $this->mkPet($customer);
        $checkIn = now()->subDay();
        $checkOut = now()->addDay();

        $ownReservation = Reservation::create([
            'code' => 'PRIVCARE001', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $room->id, 'pet_id' => $ownPet->id,
            'check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => 2,
            'total_price' => 2000, 'status' => 'confirmed',
        ]);
        $otherReservation = Reservation::create([
            'code' => 'PRIVCARE002', 'user_id' => $customer->id, 'hotel_id' => $otherHotel->id,
            'room_type_id' => $otherRoom->id, 'pet_id' => $otherPet->id,
            'check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => 2,
            'total_price' => 2000, 'status' => 'confirmed',
        ]);
        DailyReport::create([
            'reservation_id' => $ownReservation->id, 'hotel_id' => $hotel->id, 'pet_id' => $ownPet->id,
            'staff_id' => $owner->id, 'report_date' => today(), 'feeding_status' => 'all',
            'toilet_status' => 'normal', 'mood_status' => 'happy', 'notes' => 'PRIVATE-OWN-REPORT',
        ]);
        DailyReport::create([
            'reservation_id' => $otherReservation->id, 'hotel_id' => $otherHotel->id, 'pet_id' => $otherPet->id,
            'staff_id' => $otherOwner->id, 'report_date' => today(), 'feeding_status' => 'all',
            'toilet_status' => 'normal', 'mood_status' => 'happy', 'notes' => 'PRIVATE-OTHER-REPORT',
        ]);

        $this->actingAs($owner)->get(route('panel.care.dashboard'))
            ->assertOk()
            ->assertSee('PRIVATE-OWN-REPORT')
            ->assertDontSee('PRIVATE-OTHER-REPORT');
    }

    public function test_partner_signup_starts_demo_and_trial_unlocks_hotel_enrollment(): void
    {
        $this->captchaPost('/partner', [
            'name' => 'Sahip', 'email' => 's@o.com', 'phone' => '0555',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'kvkk' => 1, 'subscription_terms' => 1,
        ], 'partner')->assertRedirect(route('panel.subscriptions'));

        $owner = User::where('email', 's@o.com')->firstOrFail();
        $this->assertSame('hotel_owner', $owner->role);
        $this->assertSame(0, Hotel::count());
        $this->assertDatabaseMissing('hotel_subscriptions', ['requested_by' => $owner->id]);
        $this->actingAs($owner)->post('/panel/otel/yeni', [])->assertRedirect(route('panel.subscriptions'));

        $plan = SubscriptionPlan::create(['name' => 'Trial Test Plan', 'monthly_price' => 500, 'active' => true]);
        $this->actingAs($owner)->post(route('panel.subscriptions.trial'), ['plan_id' => $plan->id])
            ->assertRedirect(route('panel.subscriptions'))->assertSessionHasNoErrors();
        $trial = HotelSubscription::where('requested_by', $owner->id)->sole();
        $this->assertSame('active', $trial->status);
        $this->assertSame('trial', $trial->payment_method);
        $this->assertSame('unpaid', $trial->payment_status);
        $this->assertTrue($trial->ends_at->between(now()->addDays(29), now()->addDays(31)));
        $this->actingAs($owner)->get('/panel/otel/yeni')->assertOk();
        $this->actingAs($owner)->get('/yonetim')->assertForbidden();
    }

    public function test_room_management_isolated_per_owner(): void
    {
        [$h, $room, $owner] = $this->mkHotel();
        $this->actingAs($owner)->post("/panel/otel/{$h->id}/oda", ['name' => 'VIP', 'pet_type' => 'cat', 'size' => 'any', 'capacity' => 1, 'units' => 2, 'base_price' => 900])->assertSessionHasNoErrors();
        $this->assertSame(2, $h->rooms()->count());
        $stranger = $this->mkUser('hotel_owner');
        $this->activateSubscription($stranger);
        $this->actingAs($stranger)->post("/panel/otel/{$h->id}/oda", ['name' => 'X', 'pet_type' => 'cat', 'size' => 'any', 'capacity' => 1, 'units' => 1, 'base_price' => 1])->assertForbidden();
    }

    public function test_contact_form_and_honeypot(): void
    {
        $this->captchaPost('/iletisim', ['name' => 'A', 'email' => 'a@b.com', 'message' => 'Merhaba'], 'contact');
        $this->assertDatabaseCount('contact_messages', 1);
        $this->captchaPost('/iletisim', ['website' => 'spam', 'name' => 'A', 'email' => 'a@b.com', 'message' => 'Spam'], 'contact');
        $this->assertDatabaseCount('contact_messages', 1);
    }
    public function test_customer_and_hotel_owner_can_chat_only_about_their_reservation(): void
    {
        Mail::fake();
        [$hotel, $room, $owner] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);
        $reservation = Reservation::create([
            'code' => 'POCHAT0001', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $room->id, 'pet_id' => $pet->id,
            'check_in' => now()->addDays(5), 'check_out' => now()->addDays(7),
            'nights' => 2, 'total_price' => 2000, 'status' => 'confirmed',
        ]);

        $this->actingAs($customer)->postJson(route('reservations.message', $reservation), ['body' => 'Rezervasyonum hakkında bilgi rica ederim.'])
            ->assertCreated()->assertJsonPath('mine', true)->assertJsonPath('sender', 'Siz');
        $this->actingAs($owner)->getJson(route('panel.reservations.messages', $reservation).'?after=0')
            ->assertOk()->assertJsonPath('messages.0.body', 'Rezervasyonum hakkında bilgi rica ederim.');
        $this->actingAs($owner)->postJson(route('panel.reservations.message', $reservation), ['body' => 'Size yardımcı olmaktan memnuniyet duyarız.'])
            ->assertCreated()->assertJsonPath('mine', true);
        $this->actingAs($customer)->getJson(route('reservations.messages', $reservation).'?after=1')
            ->assertOk()->assertJsonPath('messages.0.body', 'Size yardımcı olmaktan memnuniyet duyarız.')
            ->assertJsonPath('messages.0.mine', false);

        $stranger = $this->mkUser();
        $this->actingAs($stranger)->getJson(route('reservations.messages', $reservation))->assertNotFound();
        $otherOwner = $this->mkUser('hotel_owner');
        $this->activateSubscription($otherOwner);
        $this->actingAs($otherOwner)->postJson(route('panel.reservations.message', $reservation), ['body' => 'Yetkisiz mesaj'])
            ->assertForbidden();
        $this->assertDatabaseCount('reservation_messages', 2);
    }

    public function test_forum_topic_and_reply_are_moderated_before_publication(): void
    {
        $author = $this->mkUser();
        $this->actingAs($author);
        $this->captchaPost('/forum/konu', [
            'category' => 'pet-bakimi', 'title' => 'Yeni dostun ilk haftası',
            'body' => 'Yeni bir evcil hayvana alışma sürecinde hangi rutinler size yardımcı oldu? Önerilerinizi paylaşabilir misiniz?',
        ], 'forum-topic')->assertRedirect(route('forum.index'));
        $topic = ForumTopic::query()->sole();
        $this->assertSame('yeni-dostun-ilk-haftasi', $topic->slug);
        $duplicateTitle = ForumTopic::create([
            'user_id' => $author->id, 'category' => 'pet-bakimi',
            'title' => 'Yeni dostun ilk haftası', 'body' => 'Aynı başlık için ayrı bir deneme konusu.',
            'status' => 'pending',
        ]);
        $this->assertSame('yeni-dostun-ilk-haftasi-2', $duplicateTitle->slug);
        $this->assertSame('pending', $topic->status);
        $this->get(route('forum.index'))->assertDontSee($topic->title);
        $this->get(route('forum.show', $topic))->assertOk()->assertSee($topic->title);

        $admin = $this->mkUser('admin');
        $this->actingAs($admin)->patch(route('admin.forum.topic', $topic), ['status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame('active', $topic->fresh()->status);
        $this->get(route('forum.index'))->assertSee($topic->title);

        $replyAuthor = $this->mkUser();
        $this->actingAs($replyAuthor);
        $this->captchaPost('/forum/konu/'.$topic->slug.'/yanit', [
            'body' => 'İlk hafta kısa ve sakin rutinler kurmak çok yardımcı oldu.',
        ], 'forum-reply')->assertRedirect();
        $reply = ForumReply::query()->sole();
        $this->assertSame('pending', $reply->status);
        $this->get(route('forum.show', $topic))->assertDontSee($reply->body);

        $this->actingAs($admin)->patch(route('admin.forum.reply', $reply), ['status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->get(route('forum.show', $topic))->assertSee($reply->body);
    }

    public function test_special_character_password_and_unconfigured_identity_providers_fail_safely(): void
    {
        $password = 'Timur2026_*';
        $this->captchaPost('/kayit', [
            'name' => 'Timur Test', 'email' => 'timur@example.test', 'password' => $password,
            'password_confirmation' => $password, 'kvkk' => 1,
        ], 'register')->assertRedirect(route('pets.create'));
        $this->assertAuthenticated();
        $this->post('/cikis')->assertRedirect('/');
        $this->post('/giris', ['identifier' => 'timur@example.test', 'password' => $password])
            ->assertRedirect(route('reservations.index'));
        $this->assertAuthenticated();

        Config::set('services.google.client_id', null);
        Config::set('services.google.client_secret', null);
        Config::set('services.google.redirect', null);
        $this->post('/cikis');
        $this->get('/giris/google')->assertRedirect(route('login'))->assertSessionHasErrors('social');

        Config::set('services.netgsm.usercode', null);
        Config::set('services.netgsm.password', null);
        Config::set('services.netgsm.header', null);
        $beforeUsers = User::count();
        $this->captchaPost('/kayit', [
            'name' => 'Telefon Üyesi', 'email' => '', 'phone' => '05551234567',
            'tc_kimlik' => '10000000078', 'password' => $password,
            'password_confirmation' => $password, 'kvkk' => 1,
        ], 'register')->assertSessionHasErrors('phone');
        $this->assertSame($beforeUsers, User::count());
        $this->assertSame(0, PendingPhoneRegistration::count());
    }

    public function test_hotel_guest_crm_only_shows_pets_and_stays_related_to_that_hotel(): void
    {
        [$hotel, $room, $owner] = $this->mkHotel();
        $guest = $this->mkUser();
        $hotelPet = $this->mkPet($guest);
        $hotelPet->update(['name' => 'Bu Oteldeki Dost']);
        $privatePet = $this->mkPet($guest);
        $privatePet->update(['name' => 'Başka Oteldeki Dost']);

        $city = City::where('slug', 'antalya')->firstOrFail();
        $district = $city->districts()->where('slug', 'kemer')->firstOrFail();
        $otherOwner = $this->mkUser('hotel_owner');
        $otherHotel = Hotel::create([
            'owner_id' => $otherOwner->id, 'name' => 'Başka İşletme', 'slug' => 'baska-isletme',
            'city_id' => $city->id, 'district_id' => $district->id, 'status' => 'active',
            'accepts_dogs' => true, 'accepts_cats' => true,
        ]);
        $otherRoom = RoomType::create(['hotel_id' => $otherHotel->id, 'name' => 'Diğer Oda', 'pet_type' => 'dog', 'size' => 'any', 'units' => 1, 'base_price' => 1000]);

        $hotelStay = Reservation::create([
            'code' => 'POCRM00001', 'user_id' => $guest->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $room->id, 'pet_id' => $hotelPet->id,
            'check_in' => now()->addDays(5), 'check_out' => now()->addDays(7), 'nights' => 2, 'total_price' => 2000,
        ]);
        $otherStay = Reservation::create([
            'code' => 'POCRM00002', 'user_id' => $guest->id, 'hotel_id' => $otherHotel->id,
            'room_type_id' => $otherRoom->id, 'pet_id' => $privatePet->id,
            'check_in' => now()->addDays(10), 'check_out' => now()->addDays(12), 'nights' => 2, 'total_price' => 2000,
        ]);
        $profiles = new \App\Services\HotelGuestProfileService();
        $profiles->recordReservation($hotelStay);
        $profiles->recordReservation($otherStay);

        $this->actingAs($owner)->get(route('panel.guests'))
            ->assertOk()->assertSee('Bu Oteldeki Dost')->assertDontSee('Başka Oteldeki Dost')
            ->assertSee('Test Otel')->assertDontSee('Başka İşletme');
    }

    public function test_pet_transfer_location_is_shared_only_during_authorized_active_transfer(): void
    {
        [$hotel, $room, $owner] = $this->mkHotel();
        $customer = $this->mkUser();
        $pet = $this->mkPet($customer);
        $reservation = Reservation::create([
            'code' => 'POTRF00001', 'user_id' => $customer->id, 'hotel_id' => $hotel->id,
            'room_type_id' => $room->id, 'pet_id' => $pet->id,
            'check_in' => now()->addDays(2), 'check_out' => now()->addDays(4),
            'nights' => 2, 'total_price' => 2000, 'status' => 'confirmed',
            'transfer_status' => 'requested',
            'transfer_type' => 'one_way', 'transfer_pickup_address' => 'Test adresi',
        ]);
        $locationUrl = route('reservations.transfer.location', $reservation);
        $this->actingAs($customer)->getJson($locationUrl)->assertOk()->assertJsonPath('active', false);

        $otherOwner = $this->mkUser('hotel_owner');
        $this->actingAs($otherOwner)->getJson($locationUrl)->assertNotFound();
        $this->actingAs($owner)->post(route('panel.reservations.transfer.start', $reservation))->assertRedirect();
        $this->postJson(route('panel.reservations.transfer.location.update', $reservation), ['lat' => 36.8969, 'lng' => 30.7133])->assertOk()->assertJsonPath('ok', true);
        $this->actingAs($customer)->getJson($locationUrl)->assertOk()
            ->assertJsonPath('active', true)->assertJsonPath('lat', 36.8969)->assertJsonPath('lng', 30.7133);

        $this->actingAs($owner)->post(route('panel.reservations.transfer.complete', $reservation))->assertRedirect();
        $this->assertNull($reservation->fresh()->transfer_lat);
        $this->assertNull($reservation->fresh()->transfer_lng);
        $this->actingAs($customer)->getJson($locationUrl)->assertOk()->assertJsonPath('active', false)->assertJsonPath('lat', null);
    }

    public function test_private_hotel_chat_is_private_and_archived_without_deletion(): void
    {
        [$hotel, , $owner] = $this->mkHotel();
        $started = time() - 2;
        $this->withSession(['captcha.hotel-chat' => ['answer' => '42', 'issued' => $started]])
            ->post(route('hotel-chats.start', $hotel->slug), [
                'name' => 'Ziyaretçi Test', 'phone' => '05305523555', 'body' => 'Kediler için odanız var mı?',
                '_captcha_answer' => '42', '_captcha_website' => '', '_captcha_started' => $started,
            ])->assertRedirect();
        $chat = \App\Models\HotelChat::with('messages')->firstOrFail();
        $this->assertSame('905305523555', \App\Support\PhoneNumber::whatsapp('05305523555'));
        $this->get(route('hotel-chats.show', $chat))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Kediler için odanız var mı?');
        $this->withSession(['private_hotel_chats' => [$chat->id => 'not-the-chat-token']])->get(route('hotel-chats.show', $chat))->assertNotFound();
        $this->get(route('panel.hotel-chats'))->assertRedirect(route('login'));
        $otherOwner = $this->mkUser('hotel_owner');
        $this->actingAs($otherOwner)->get(route('hotel-chats.show', $chat))->assertNotFound();
        $this->actingAs($owner)->get(route('hotel-chats.show', $chat))->assertOk();
        $this->post(route('hotel-chats.send', $chat), ['body' => 'Evet, ayrı kedi odalarımız mevcut.'])->assertRedirect();
        $this->actingAs($owner)->getJson(route('hotel-chats.poll', $chat).'?after=1')->assertOk()->assertJsonPath('messages.0.body', 'Evet, ayrı kedi odalarımız mevcut.');
        $this->post(route('hotel-chats.archive', $chat))->assertRedirect();
        $this->assertDatabaseHas('hotel_chats', ['id' => $chat->id, 'status' => 'archived']);
        $this->assertDatabaseHas('hotel_chat_messages', ['hotel_chat_id' => $chat->id, 'body' => 'Kediler için odanız var mı?']);
        $this->actingAs($owner)->get(route('panel.hotel-chats').'?arsiv=1')->assertOk()->assertSee('Ziyaretçi Test');
        $admin = $this->mkUser('admin');
        $this->actingAs($admin)->get(route('admin.hotel-chats').'?arsiv=1')->assertOk()->assertSee('Ziyaretçi Test');
    }

    public function test_lost_pet_public_sharing_is_opt_in_and_phone_requires_separate_consent(): void
    {
        $owner = $this->mkUser();
        $pet = $this->mkPet($owner);
        $service = \Mockery::mock(GeofencedAlertService::class)->makePartial();
        $service->shouldReceive('notify')->once()->andReturn(0);
        $this->app->instance(GeofencedAlertService::class, $service);

        $response = $this->actingAs($owner)->post(route('pets.lost-alerts.store', $pet), [
            'lat' => 36.8969, 'lng' => 30.7133, 'note' => 'Kırmızı tasma',
            'public_listing' => '1', 'share_phone' => '1', 'public_phone' => '+90 555 111 22 33',
        ]);

        $alert = GeofencedAlert::where('pet_id', $pet->id)->firstOrFail();
        $response->assertRedirect(route('lost-pets.show', $alert));
        $alert->refresh();
        $this->assertTrue($alert->is_public);
        $this->assertSame('Kırmızı tasma', $alert->public_note);
        $this->assertSame('+90 555 111 22 33', $alert->public_contact_phone);
    }

    public function test_private_lost_pet_report_does_not_store_public_note_or_phone(): void
    {
        $owner = $this->mkUser();
        $pet = $this->mkPet($owner);
        $service = \Mockery::mock(GeofencedAlertService::class)->makePartial();
        $service->shouldReceive('notify')->once()->andReturn(0);
        $this->app->instance(GeofencedAlertService::class, $service);

        $this->actingAs($owner)->from('/evcil-hayvanlarim/'.$pet->id.'/saglik')->post(route('pets.lost-alerts.store', $pet), [
            'lat' => 36.8969, 'lng' => 30.7133, 'note' => 'Özel not',
        ])->assertRedirect('/evcil-hayvanlarim/'.$pet->id.'/saglik')->assertSessionHasNoErrors();

        $alert = GeofencedAlert::where('pet_id', $pet->id)->firstOrFail();
        $this->assertFalse($alert->is_public);
        $this->assertNull($alert->public_note);
        $this->assertNull($alert->public_contact_phone);
        $this->get(route('lost-pets.index'))->assertDontSee('Özel not');
    }

    public function test_public_lost_pet_pages_hide_exact_coordinates_and_only_show_active_shared_alerts(): void
    {
        $owner = $this->mkUser();
        $publicPet = $this->mkPet($owner);
        $publicPet->update(['name' => 'Luna Açık']);
        $publicAlert = GeofencedAlert::create([
            'pet_id' => $publicPet->id, 'lat' => 36.8969123, 'lng' => 30.7133456, 'radius_m' => 5000,
            'status' => 'open', 'reported_at' => now(), 'is_public' => true, 'public_note' => 'Kırmızı tasma',
            'notified_user_ids' => [$owner->id],
        ]);
        $privatePet = $this->mkPet($owner);
        $privatePet->update(['name' => 'Gizli İlan']);
        GeofencedAlert::create([
            'pet_id' => $privatePet->id, 'lat' => 36.8969123, 'lng' => 30.7133456, 'radius_m' => 5000,
            'status' => 'open', 'reported_at' => now(), 'is_public' => false,
        ]);

        $this->get(route('lost-pets.index'))
            ->assertOk()->assertSee('Luna Açık')->assertDontSee('Gizli İlan')
            ->assertSee('noindex, follow', false);
        $this->get(route('lost-pets.show', $publicAlert))
            ->assertOk()->assertSee('36.90, 30.71')
            ->assertDontSee('36.8969123');

        $this->actingAs($owner)->patch(route('pets.lost-alerts.resolve', $publicAlert))->assertRedirect();
        $this->get(route('lost-pets.show', $publicAlert))->assertNotFound();
    }
}
