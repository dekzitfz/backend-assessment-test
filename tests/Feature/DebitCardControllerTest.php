<?php

namespace Tests\Feature;

use App\Models\DebitCard;
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
        $numberOfDebitCards = 3;
        $debitCards = DebitCard::factory($numberOfDebitCards)
            ->active()
            ->for($this->user)
            ->create();

        // get /debit-cards
        $response = $this->get('/api/debit-cards');

        $response->assertStatus(200)
            ->assertJsonCount($numberOfDebitCards)
            ->assertJsonStructure(['*' => ['id', 'number', 'type', 'expiration_date', 'is_active']])
            ->assertJsonFragment(['number' => $debitCards->first()->value('number')]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $otherCustomer = User::factory()->create();
        DebitCard::factory()
            ->active()
            ->for($otherCustomer)
            ->create();

        // get /debit-cards
        $response = $this->get('/api/debit-cards');

        $response->assertStatus(200)
            ->assertJsonCount(0)
            ->assertJsonStructure([]);
    }

    public function testCustomerCanCreateADebitCard()
    {
        $payload = [
            'type' => 'visa',
        ];

        // post /debit-cards
        $response = $this->post('/api/debit-cards', $payload);

        $response->assertStatus(201)
            ->assertJson($payload)
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $response->json('id'),
            'user_id' => $this->user->id,
            'type' => $payload["type"],
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
    }

    // Extra bonus for extra tests :)
}
