<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
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
        DebitCard::factory()->count(10)->create(['user_id' => $this->user->id]);
        $response = $this->getJson('/api/debit-cards');
        $response->assertStatus(200);

        //return data should be more than 0 but it could be less than 10 because on the factory some debit cards might be non active
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $otherUser = User::factory()->create();

        DebitCard::factory()->count(10)->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertOk();
        $response->assertJsonCount(0); // return data should be 0
    }

    public function testCustomerCanCreateADebitCard()
    {
        $data = [
            'type' => 'MasterCard',
        ];

        $response = $this->postJson('/api/debit-cards', $data);
        $response->assertCreated();
        $this->assertDatabaseHas('debit_cards', $data + ['user_id' => $this->user->id]); // check if theres debit cards with this user id
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertOk();
        $response->assertJson([
            'id' => $debitCard->id,
            'number' => $debitCard->number,
            'type' => $debitCard->type,
            'is_active' => $debitCard->is_active,
        ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertForbidden(); // should return unauthorized action
    }

    public function testCustomerCanActivateADebitCard()
    {
        $debitCard = DebitCard::factory()->expired()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => true]);

        $response->assertOk();
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => null
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCard = DebitCard::factory()->active()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => false]);

        $response->assertOk();
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
        ]);

        $this->assertNotNull(DebitCard::find($debitCard->id)->disabled_at);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => null]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['is_active']); // should return error validation when is_active is null or non boolean
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('debit_cards', ['id' => $debitCard->id]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        DebitCardTransaction::factory()->create(['debit_card_id' => $debitCard->id]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('debit_cards', ['id' => $debitCard->id]); // the debit card should still be exist
    }

    // Extra bonus for extra tests :)

    public function testCustomerCannotDeleteDebitCardOfOtherCustomers()
    {
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->for($otherUser)->create();

        $response = $this->deleteJson("/api/debit-cards/{$otherDebitCard->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('debit_cards', ['id' => $otherDebitCard->id]);
    }
}
