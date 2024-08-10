<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
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
        // get /debit-cards
        $numberDebitCards = 5;
        $debitCards = DebitCard::factory($numberDebitCards)
            ->active()
            ->for($this->user)
            ->create();

        $response = $this->get('/api/debit-cards');

        $response->assertStatus(200)
            ->assertJsonCount($numberDebitCards)
            ->assertJsonStructure(['*' => ['id', 'number', 'type', 'expiration_date', 'is_active']])
            ->assertJsonFragment(['number' => $debitCards->first()->value('number')]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        $otherCustomer = User::factory()->create();
        DebitCard::factory()
            ->active()
            ->for($otherCustomer)
            ->create();

        $response = $this->get('/api/debit-cards');

        $response->assertStatus(200)
            ->assertJsonCount(0)
            ->assertJsonStructure([]);
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $payload = [
            'type' => 'masterCard',
        ];

        $response = $this->post('/api/debit-cards', $payload);

        $response->assertStatus(201)
            ->assertJson($payload)
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $response->json('id'),
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()
            ->active()
            ->for($this->user)
            ->create();

        $response = $this->get("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->for($otherUser)->create();

        $response = $this->get("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->for($this->user)->create();
        $payload = [
            'is_active' => true,
        ];

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active'])
            ->assertJsonFragment(['is_active' => true]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => null,
        ]);

        $debitCard->refresh();
        $this->assertTrue($debitCard->is_active);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->active()->for($this->user)->create();
        $payload = [
            'is_active' => false,
        ];

        // put api/debit-cards/{debitCard}
        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active'])
            ->assertJsonFragment(['is_active' => false]);

        $debitCard->refresh();
        $this->assertFalse($debitCard->is_active);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->active()->for($this->user)->create();
        $payload = [
            'is_active' => false,
        ];

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active'])
            ->assertJsonFragment(['is_active' => false]);

        $debitCard->refresh();
        $this->assertFalse($debitCard->is_active);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->for($this->user)->create();

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('debit_cards', [
            'id' => $debitCard->id
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}

        $numberOfTransactions = 5;
        $debitCard = DebitCard::factory()
            ->active()
            ->for($this->user)
            ->has(DebitCardTransaction::factory($numberOfTransactions))
            ->create();

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
        ]);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $debitCard->id,
        ]);
    }

    // Extra bonus for extra tests :)

    public function testCustomerCannotAccessDebitCardOfOtherUser()
    {
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/debit-cards/' . $debitCard->id);
        
        $response->assertStatus(403);
    }

    public function testCustomerCannotUpdateDebitCardWithInvalidData()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
        ]);
        
        $response = $this->actingAs($this->user, 'api')
            ->putJson('/api/debit-cards/' . $debitCard->id, [
                'number' => 'invalid',
                'type' => '',
                'expiration_date' => 'invalid-date',
                'is_active' => 'not-a-boolean',
            ]);
        
        $response->assertStatus(422);
    }
}
