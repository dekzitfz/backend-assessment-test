<?php

namespace Tests\Feature;

use Carbon\Carbon;
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
        // get /debit-cards
        $response = $this->get('/api/debit-cards');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
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
        $owningUser = User::factory()->create();
        $owningDebitCard = DebitCard::factory()->create(['user_id' => $owningUser->id]);

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        // Acting as the owning user
        Passport::actingAs($owningUser);

        $response = $this->getJson('/api/debit-cards');
        $response->assertStatus(200);

        $response->assertJsonMissing([
            'id' => $otherDebitCard->id,
            'number' => $otherDebitCard->number,
            'type' => $otherDebitCard->type,
            'expiration_date' => $otherDebitCard->expiration_date,
        ]);
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $response = $this->postJson('/api/debit-cards', [
            'type' => 'visa',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'id',
                    'type',
                    'number',
                    'expiration_date',
                    'is_active',
                ]);

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'visa',
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/debit-cards/' . $debitCard->id);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                    'id',
                    'type',
                    'number',
                    'expiration_date',
                    'is_active',
                ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-cards/' . $debitCard->id);

        $response->assertStatus(403);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id, 'disabled_at' => Carbon::now()
        ]);

        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => true
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['is_active' => true]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => null
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => false
        ]);

        $response->assertStatus(200)
                ->assertJsonFragment([
                    'is_active' => false
                ]);

        $this->assertDatabaseMissing('debit_cards', [
            'id' => $debitCard->id,
            'disabled_at' => null
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => 'not-a-boolean'
        ]);

        $response->assertStatus(422);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson('/api/debit-cards/' . $debitCard->id);

        $response->assertStatus(204);

        $this->assertSoftDeleted('debit_cards', [
            'id' => $debitCard->id,
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        DebitCardTransaction::factory()->create(['debit_card_id' => $debitCard->id]);

        $response = $this->deleteJson('/api/debit-cards/' . $debitCard->id);

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
    public function testCustomerCanViewTheirNoDebitCards()
    {
        // get /debit-cards
        $response = $this->getJson('/api/debit-cards');
        $response
            ->assertOk()
            ->assertJson([])
            ->assertJsonMissing([
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ]);

        $this->assertDatabaseMissing('debit_cards', [
            'user_id' => $this->user->id,
        ]);
    }

    public function testCustomerCannotCreateADebitCardIfOneOfFieldIsMissing()
    {
        $response = $this->postJson('/api/debit-cards', [
            // 'type' => 'visa',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        $this->assertDatabaseMissing('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'visa',
        ]);
    }

}
