<?php

namespace Tests\Feature;

use App\Models\{DebitCard, DebitCardTransaction, User};
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
        // create Debit Card Transaction
        DebitCardTransaction::factory()->count(5)->for($this->debitCard)->create();

        // get /debit-card-transactions
        $response = $this->get("api/debit-card-transactions?debit_card_id={$this->debitCard->id}");

        // response code 200
        $response->assertStatus(200);

        // 0 also can see list debit
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // create a user
        $other_user = User::factory()->create();

        // create other customer Debit Card -> relation belongs to debitcard
        $other_user_debit_card_transaction = DebitCardTransaction::factory()->count(5)
            ->for(DebitCard::factory()->state([
                'user_id' => $other_user->id,
            ]))
            ->create();

        // Debit Card History Transaction data searching using $this->user
        // get /debit-card-transactions
        $response = $this->get("api/debit-card-transactions?debit_card_id={$other_user_debit_card_transaction->first()->debit_card_id}");

        // if have credit card but not it $this->user
        $response->assertForbidden();
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        $other_user_debit_card_transaction = DebitCardTransaction::factory()->count(5)
            ->for(DebitCard::factory()->state([
                'user_id' => $this->user->id,
            ]))
            ->create();
        
        $post_data = [
            'debit_card_id' => $other_user_debit_card_transaction->first()->debit_card_id,
            'amount' => 10000,
            'currency_code' => 'IDR',
        ];

        // post /debit-card-transactions
        $response = $this->post('api/debit-card-transactions', $post_data);

        // check response
        $response->assertStatus(201);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
    }

    // Extra bonus for extra tests :)
}
