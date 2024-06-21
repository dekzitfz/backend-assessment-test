<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        DebitCard::factory()
            ->count(3)
            ->active()
            ->for($this->user)
            ->create();

        $response = $this->getJson('api/debit-cards');
        $response
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure(['*' => ['id', 'number', 'type', 'expiration_date', 'is_active']]);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $response = $this->getJson('api/debit-cards');
        $response
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function testCustomerCanCreateADebitCard()
    {
        $response = $this->postJson('api/debit-cards', ['type' => 'foo']);
        $response
            ->assertCreated()
            ->assertJson(['type' => 'foo'])
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'type' => 'foo',
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()
            ->active()
            ->for($this->user)
            ->create();

        $response = $this->getJson("api/debit-cards/{$debitCard->id}");
        $response
            ->assertOk()
            ->assertJson(['number' => $debitCard->number, 'type' => $debitCard->type])
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $this->expectException(ModelNotFoundException::class);

        $this->withoutExceptionHandling()->getJson('api/debit-cards/1');
    }

    public function testCustomerCanActivateADebitCard()
    {
        $debitCard = DebitCard::factory()
            ->expired()
            ->for($this->user)
            ->create();

        $response = $this->putJson("api/debit-cards/{$debitCard->id}", ['is_active' => true]);
        $response
            ->assertOk()
            ->assertJson(['number' => $debitCard->number, 'type' => $debitCard->type, 'is_active' => true])
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'disabled_at' => null,
            'type' => $debitCard->type,
            'number' => $debitCard->number,
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCard = DebitCard::factory()
            ->active()
            ->for($this->user)
            ->create();

        $response = $this->putJson("api/debit-cards/{$debitCard->id}", ['is_active' => false]);
        $response
            ->assertOk()
            ->assertJson(['number' => $debitCard->number, 'type' => $debitCard->type, 'is_active' => false])
            ->assertJsonStructure(['id', 'number', 'type', 'expiration_date', 'is_active']);

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'disabled_at' => Carbon::now(),
            'type' => $debitCard->type,
            'number' => $debitCard->number,
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()
            ->active()
            ->for($this->user)
            ->create();

        $response = $this->putJson("api/debit-cards/{$debitCard->id}", ['is_active' => 'foo']);
        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()
            ->active()
            ->for($this->user, 'user')
            ->create();

        $response = $this->deleteJson("api/debit-cards/{$debitCard->id}");
        $response->assertNoContent();

        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $this->user->id,
            'deleted_at' => Carbon::now(),
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()
            ->active()
            ->for($this->user)
            ->has(DebitCardTransaction::factory()->count(5), 'debitCardTransactions')
            ->create();

        $response = $this->deleteJson("api/debit-cards/{$debitCard->id}");
        $response->assertForbidden();
    }
}
