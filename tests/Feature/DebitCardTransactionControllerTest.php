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
        // get /debit-card-transactions
        $transactions = DebitCardTransaction::factory()->count(3)->create([
            'debit_card_id' => $this->debitCard->id,
        ]);

        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $this->debitCard->id);

        $response->assertStatus(200);

        foreach ($transactions as $transaction) {
            $response->assertJsonFragment([
                'amount' => (string)$transaction->amount, // Cast amount to integer
                'currency_code' => $transaction->currency_code,
            ]);
        }
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-card-transactions', [
            'debit_card_id' => $otherDebitCard->id,
        ]);

        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 1000,
            'currency_code' => 'SGD',
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertCreated()
            ->assertJsonFragment([
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'],
            ]);

        $this->assertDatabaseHas('debit_card_transactions', [
            'amount' => $data['amount'],
            'currency_code' => $data['currency_code'],
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $data = [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 1000,
            'currency_code' => 'SGD',
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('debit_card_transactions', [
            'amount' => $data['amount'],
            'currency_code' => $data['currency_code'],
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id,
        ]);

        $response = $this->getJson('/api/debit-card-transactions/' . $transaction->id);

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'amount' => (string)$transaction->amount,
            'currency_code' => $transaction->currency_code,
        ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $otherDebitCardTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id,
        ]);

        $response = $this->getJson('/api/debit-card-transactions/' . $otherDebitCardTransaction->id);

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)
    public function testCustomerCannotCreateADebitCardTransactionIfOneOfFieldIsMissing()
    {
        $data = [
            'debit_card_id' => $this->debitCard->id,
            // 'amount' => 1000,
            'currency_code' => 'SGD',
        ];

        $response = $this->postJson('/api/debit-card-transactions', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $this->debitCard->id,
            'currency_code' => 'SGD',
        ]);
    }

}
