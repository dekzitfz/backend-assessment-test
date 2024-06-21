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
        $id = $this->debitCard->id;
        DebitCardTransaction::factory(5)->create(['debit_card_id' => $id]);

        $res = $this->get("/api/debit-card-transactions?debit_card_id=$id");

        $res->assertStatus(200)
            ->assertJsonStructure([
                [
                    'amount',
                    'currency_code',
                ]
            ]);

    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        DebitCardTransaction::factory()->count(3)->create(['debit_card_id' => $otherDebitCard->id]);

        $res = $this->get('/api/debit-card-transactions?debit_card_id=' . $otherDebitCard->id);

        $res->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 100.00,
            'currency_code' => 'IDR'
        ];

        $res = $this->post('/api/debit-card-transactions', $data);
        $res->assertStatus(201)
                 ->assertJsonStructure([
                    'amount',
                    'currency_code',
                ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $data = [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 100.00,
            'description' => 'Test Transaction'
        ];

        $res = $this->post('/api/debit-card-transactions', $data);

        $res->assertStatus(403);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $debitCardTransaction = DebitCardTransaction::factory()->for($this->debitCard)->create();

        $this->get('/api/debit-card-transactions/' . $debitCardTransaction->id)
            ->assertJsonStructure([
                'amount',
                'currency_code',
            ])
            ->assertOk();
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $transaction = DebitCardTransaction::factory()->create(['debit_card_id' => $otherDebitCard->id]);

        $res = $this->get('/api/debit-card-transactions/' . $transaction->id);

        $res->assertStatus(403);
    }

    // Extra bonus for extra tests :)
}
