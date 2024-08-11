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
        $numberOfTransactions = 3;
        DebitCardTransaction::factory($numberOfTransactions)->for($this->debitCard)->create();

        $response = $this->getJson("api/debit-card-transactions?debit_card_id={$this->debitCard->id}");

        $response->assertStatus(200)
            ->assertJsonCount($numberOfTransactions)
            ->assertJsonStructure(['*' => ['amount', 'currency_code']]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        $otherCustomer = User::factory()->create();
        $debitCard = DebitCard::factory()->for($otherCustomer)->create();

        $response = $this->getJson("api/debit-card-transactions?debit_card_id={$debitCard->id}");

        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 95000,
            'currency_code' => 'IDR',
        ];

        $response = $this->postJson('/api/debit-card-transactions', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['amount', 'currency_code'])
            ->assertJson([
                'amount' => $payload["amount"],
                'currency_code' => $payload["currency_code"],
            ]);

        $this->assertDatabaseHas('debit_card_transactions', $payload);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        $otherCustomer = User::factory()->create();
        $debitCard = DebitCard::factory()->for($otherCustomer)->create();
        $payload = [
            'debit_card_id' => $debitCard->id,
            'amount' => 1000,
            'currency_code' => 'IDR',
        ];

        // post /debit-card-transactions
        $response = $this->postJson('api/debit-card-transactions', $payload);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $debitCard->id,
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        $transaction = DebitCardTransaction::factory()->for($this->debitCard)->create();
                // get /debit-card-transactions/{debitCardTransaction}
        $response = $this->getJson("api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['amount', 'currency_code'])
            ->assertJson(['amount' => $transaction->amount, 'currency_code' => $transaction->currency_code]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        $otherCustomer = User::factory()->create();
        $debitCard = DebitCard::factory()->for($otherCustomer)->create();
        $transaction = DebitCardTransaction::factory()->for($debitCard)->create();

        $response = $this->getJson("api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}
