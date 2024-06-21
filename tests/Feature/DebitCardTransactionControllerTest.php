<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        DebitCardTransaction::factory()->count(5)->for($this->debitCard)->create();

        $response = $this->getJson("api/debit-card-transactions?debit_card_id={$this->debitCard->id}");
        $response
            ->assertOk()
            ->assertJsonCount(5)
            ->assertJsonStructure(['*' => ['amount', 'currency_code']]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        $debitCard = DebitCard::factory()->create();

        $response = $this->getJson("api/debit-card-transactions?debit_card_id={$debitCard->id}");
        $response->assertForbidden();
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        $response = $this->postJson('api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 2000,
            'currency_code' => DebitCardTransaction::CURRENCY_VND
        ]);

        $response
            ->assertCreated()
            ->assertJson(['amount' => 2000, 'currency_code' => DebitCardTransaction::CURRENCY_VND])
            ->assertJsonStructure(['amount', 'currency_code']);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 2000,
            'currency_code' => DebitCardTransaction::CURRENCY_VND
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        $debitCard = DebitCard::factory()->create();

        $response = $this->postJson('api/debit-card-transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 2000,
            'currency_code' => DebitCardTransaction::CURRENCY_VND
        ]);

        $response->assertForbidden();
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        $transaction = DebitCardTransaction::factory()->for($this->debitCard)->create();

        $response = $this->getJson("api/debit-card-transactions/{$transaction->id}?debit_card_id={$this->debitCard->id}");
        $response
            ->assertOk()
            ->assertJson(['amount' => $transaction->amount, 'currency_code' => $transaction->currency_code])
            ->assertJsonStructure(['amount', 'currency_code']);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        $debitCard = DebitCard::factory()->create();
        $transaction = DebitCardTransaction::factory()->for($debitCard)->create();

        $response = $this->getJson("api/debit-card-transactions/{$transaction->id}?debit_card_id={$debitCard->id}");
        $response->assertForbidden();
    }
}
