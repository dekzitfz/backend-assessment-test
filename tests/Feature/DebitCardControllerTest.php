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

        $response = $this->get('/api/debit-cards');

        $response->assertStatus(200)
            ->assertJsonCount(0)
            ->assertJsonStructure([]);
    }

    public function testCustomerCanCreateADebitCard()
    {
        $payload = [
            'type' => 'mastercard',
        ];

        // post /debit-cards
        $response = $this->post('/api/debit-cards', $payload);

        $response->assertStatus(201)
            ->assertJson($payload)
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $response->json('id'),
            'number' => $response->json('number')
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()
        ->active()
        ->for($this->user)
        ->create();

    $response = $this->get("/api/debit-cards/{$debitCard->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active'])
        ->assertJsonFragment([
            'id' => $debitCard->id,
            'number' => $debitCard->number,
            'type' => $debitCard->type,
            'is_active' => $debitCard->is_active
        ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->for($otherUser)->create();

        $response = $this->get("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
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

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()->for($this->user)->create();
        $payload = [
            'is_active' => "active",
        ];

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors("is_active");
    }

    public function testCustomerCanDeleteADebitCard()
    {
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
    }

    // Extra bonus for extra tests :)
}
