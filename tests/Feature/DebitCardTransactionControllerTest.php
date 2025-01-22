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
        DebitCardTransaction::factory(3)->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $this->assertDatabaseCount('debit_card_transactions', 3);

        $res = $this->getJson('api/debit-card-transactions?debit_card_id='.$this->debitCard->id);
        $res->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['amount', 'currency_code']
            ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);
        $otherDebitCardTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id
        ]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $otherDebitCard->id,
            'user_id' => $otherUser->id
        ]);
        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $otherDebitCard->id
        ]);

        $res = $this->getJson('api/debit-card-transactions?debit_card_id='.$this->debitCard->id);
        $res->assertStatus(200)
            ->assertJsonCount(0)
            ->assertJsonMissing($otherDebitCardTransaction->toArray());
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 33000,
            'currency_code' => 'IDR'
        ];

        $res = $this->postJson('api/debit-card-transactions', $data);
        $res->assertStatus(201)
            ->assertJsonFragment([
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code']
            ]);

        $this->assertDatabaseHas('debit_card_transactions', $data);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $otherDebitCard->id,
            'user_id' => $otherUser->id
        ]);

        $data = [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 33000,
            'currency_code' => 'IDR'
        ];

        $res = $this->postJson('api/debit-card-transactions', $data);
        $res->assertStatus(403);

        $this->assertDatabaseMissing('debit_card_transactions', $data);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $debitCardTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $this->assertDatabaseHas('debit_card_transactions', [
            'id' => $debitCardTransaction->id,
        ]);
        
        $res = $this->getJson('api/debit-card-transactions/'.$debitCardTransaction->id);
        $res->assertStatus(200)
            ->assertJsonFragment([
                'currency_code' => $debitCardTransaction->currency_code,
                'amount' => (string) $debitCardTransaction->amount,
            ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);
        $otherDebitCardTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id
        ]);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $otherDebitCard->id,
            'user_id' => $otherUser->id
        ]);
        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $otherDebitCard->id
        ]);

        $res = $this->getJson('api/debit-card-transactions/'.$otherDebitCardTransaction->id);
        $res->assertStatus(403)
            ->assertJsonMissing($otherDebitCardTransaction->toArray());
    }

    // Extra bonus for extra tests :)
    public function testCustomerCannotCreateADebitCardTransactionWithWrongValidation()
    {
        // post /debit-card-transactions
        $data = [
            'debit_card_id' => $this->debitCard->id,
        ];

        $res = $this->postJson('api/debit-card-transactions', $data);
        $res->assertStatus(422)
            ->assertJsonValidationErrorFor('amount')
            ->assertJsonValidationErrorFor('currency_code');

        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 'string',
        ];

        $res = $this->postJson('api/debit-card-transactions', $data);
        $res->assertStatus(422)
            ->assertJsonValidationErrorFor('amount')
            ->assertJsonValidationErrorFor('currency_code');

        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 'string',
            'currency_code' => 'TES'
        ];

        $res = $this->postJson('api/debit-card-transactions', $data);
        $res->assertStatus(422)
            ->assertJsonValidationErrorFor('amount')
            ->assertJsonValidationErrorFor('currency_code');
    }
}
