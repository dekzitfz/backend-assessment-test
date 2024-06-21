<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Carbon\Carbon;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        DebitCard::factory(5)->create(['user_id' => $this->user->id]);

        $res = $this->get('/api/debit-cards');

        $res->assertStatus(200)
            ->assertJsonStructure([
                [
                    'id',
                    'number',
                    'type',
                    'expiration_date',
                    'is_active',
                ]
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        $anotherUser = User::factory()->create();
        DebitCard::factory(4)->for($anotherUser)->create();
        DebitCard::factory(5)->create(['user_id' => $this->user->id]);
        $res = $this->get('/api/debit-cards');
        $res->assertStatus(200);
        $res->assertOk();
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $this->post('/api/debit-cards', [
            'user_id' => $this->user->id,
            'type' => 'card type',
            'number' => rand(1000000000000000, 9999999999999999),
            'expiration_date' => Carbon::now()->addYear(),
        ])->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'card type',
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        $this->get('/api/debit-cards/' . $debitCard->id)
            ->assertJsonStructure([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ])
            ->assertOk();
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $anotherUser = User::factory()->create();
        Passport::actingAs($anotherUser);

        $res = $this->get("/api/debit-cards/$debitCard->id");

        $res->assertStatus(403)->assertForbidden();

    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = $this->user->debitCards()->create([
            'type' => 'visa',
            'number' => '1234567890123456',
            'disabled_at' => now(),
            'expiration_date' => Carbon::now()->addYear(),
        ]);

        $res = $this->putJson("/api/debit-cards/$debitCard->id", ['is_active' => true]);

        $res->assertStatus(200);
        $this->assertTrue($debitCard->fresh()->is_active);
    }

    public function testCustomerCanDeactivateADebitCard()
    {

        $debitCard =  $debitCard = $this->user->debitCards()->create([
            'type' => 'visa',
            'number' => '1234567890123456',
            'disabled_at' => null,
            'expiration_date' => Carbon::now()->addYear(),
        ]);
        $res = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => false,
        ]);
        $res->assertStatus(200);
        $this->assertFalse($debitCard->fresh()->is_active);

    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        $res = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => null,
        ]);
        $res->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        $res = $this->deleteJson('/api/debit-cards/' . $debitCard->id);
        $res->assertStatus(204);
        $this->assertSoftDeleted('debit_cards', [
            'id' => $debitCard->id,
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        DebitCardTransaction::factory()->for($debitCard)->create();
        $res = $this->deleteJson('/api/debit-cards/' . $debitCard->id);
        $res->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}
