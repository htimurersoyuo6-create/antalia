<?php

namespace Tests\Feature;

use App\Models\AdoptionListing;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use App\Http\Middleware\ExternalWebsiteNofollow;
use Tests\TestCase;

class AdoptionReadAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection('adoption');
        $schema->dropIfExists('adoption_listings');
        $schema->create('adoption_listings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('pet_name', 100);
            $table->string('species', 20);
            $table->string('breed', 100)->nullable();
            $table->unsignedSmallInteger('age_months')->nullable();
            $table->string('sex', 20)->nullable();
            $table->boolean('vaccinated')->default(false);
            $table->string('city', 100)->default('Antalya');
            $table->string('district', 100)->nullable();
            $table->text('description');
            $table->string('contact_phone', 30)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('adopted_at')->nullable();
            $table->timestamps();
            $table->index(['species', 'city', 'status']);
        });
    }

    public function test_index_filters_and_paginates_active_listings_only(): void
    {
        $owner = $this->makeUser();
        for ($i = 1; $i <= 13; $i++) {
            $this->makeListing($owner, ['pet_name' => 'Pet '.$i, 'breed' => 'Tekir']);
        }
        $this->makeListing($owner, ['pet_name' => 'Dog', 'species' => 'dog', 'breed' => 'Tekir']);
        $this->makeListing($owner, ['pet_name' => 'Pending', 'breed' => 'Tekir', 'status' => 'pending']);
        $this->makeListing($owner, ['pet_name' => 'Other city', 'breed' => 'Tekir', 'city' => 'Alanya']);

        $response = $this->withoutMiddleware(ExternalWebsiteNofollow::class)
            ->get('/sahiplendir?species=cat&city=Antalya&q=Tekir&page=2');

        $response->assertOk();
        $listings = $response->viewData('listings');
        $this->assertSame(13, $listings->total());
        $this->assertSame(2, $listings->currentPage());
        $this->assertSame(1, $listings->count());
        $this->assertStringContainsString('species=cat', $listings->url(1));
        $this->assertStringContainsString('q=Tekir', $listings->url(1));
    }

    public function test_detail_is_public_only_for_active_owner_or_admin(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $admin = $this->makeUser('admin');
        $active = $this->makeListing($owner, ['pet_name' => 'Public pet']);
        $pending = $this->makeListing($owner, ['pet_name' => 'Private pet', 'status' => 'pending']);
        $disclosedVaccination = $this->makeListing($owner, ['pet_name' => 'Vaccination disclosure', 'vaccinated' => true]);

        $this->get(route('adoption.show', $active))->assertOk()->assertSee('Public pet');
        $this->get(route('adoption.show', $disclosedVaccination))
            ->assertOk()
            ->assertSee('İlan sahibinin beyanına göre aşılı');
        $this->get(route('adoption.show', $pending))->assertNotFound();
        $this->actingAs($owner)->get(route('adoption.show', $pending))->assertOk()->assertSee('Private pet');
        $this->actingAs($stranger)->get(route('adoption.show', $pending))->assertNotFound();
        $this->actingAs($admin)->get(route('adoption.show', $pending))->assertOk()->assertSee('Private pet');
    }

    public function test_public_adoption_detail_handles_a_missing_listing_account_without_exposing_contact_data(): void
    {
        $owner = $this->makeUser();
        $listing = $this->makeListing($owner, ['contact_phone' => '+90 555 111 22 33']);
        $owner->delete();

        $this->get(route('adoption.show', $listing))
            ->assertOk()
            ->assertSee('İlanı veren hesabı artık bulunamadığı için iletişim bilgisi gösterilemiyor.')
            ->assertDontSee('+90 555 111 22 33');
    }

    private function makeUser(string $role = 'customer'): User
    {
        $user = new User([
            'name' => 'Test owner',
            'email' => bin2hex(random_bytes(8)).'@example.test',
            'password' => 'test-password',
        ]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function makeListing(User $owner, array $overrides = []): AdoptionListing
    {
        return AdoptionListing::create(array_merge([
            'user_id' => $owner->id,
            'pet_name' => 'Mavi',
            'species' => 'cat',
            'breed' => 'Tekir',
            'city' => 'Antalya',
            'district' => 'Muratpasa',
            'description' => 'A friendly pet looking for a safe and caring home.',
            'status' => 'active',
        ], $overrides));
    }
}
