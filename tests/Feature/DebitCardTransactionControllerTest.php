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
        // create a debit card transaction
        $transactions = DebitCardTransaction::factory(10)->create([
            "debit_card_id" => $this->debitCard->id
        ]);

        // get /debit-card-transactions
        $response = $this->getJson("api/debit-card-transactions?debit_card_id={$this->debitCard->id}");

         // expected response code is 200
        $response->assertStatus(200);

        // check if actual value (from response) is greater than expected
        $this->assertGreaterThan(0, count($response->json()));

    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // create another user
        $user2 = User::factory()->create();

        // create anoter debit card
        $debitCard2 = DebitCard::factory()->for($user2)->create();

        // create a debit card transaction
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard2->id,
        ]);

        // get /debit-card-transactions
        $response = $this->getJson("api/debit-card-transactions?debit_card_id={$debitCard2->id}");

         // expected response code is 403
        $response->assertStatus(403);
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // prepare payload data
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 9999,
            'currency_code' => 'IDR',
        ];
        // post /debit-card-transactions
        $response = $this->postJson("api/debit-card-transactions", $payload);

        $response->assertStatus(201);


        $response->assertJson([
            'amount' => $payload["amount"],
            'currency_code' => $payload["currency_code"],
        ]);

        $this->assertDatabaseHas("debit_card_transactions", $payload);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
         // create another user
         $user2 = User::factory()->create();

         // create anoter debit card
         $debitCard2 = DebitCard::factory()->for($user2)->create();

         // prepare payload data
        $payload = [
            'debit_card_id' => $debitCard2->id,
            'amount' => 8888,
            'currency_code' => 'IDR',
        ];
         // post /debit-card-transactions
         $response = $this->postJson("api/debit-card-transactions", $payload);

         $response->assertStatus(403);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // create transactions
        $transactions = DebitCardTransaction::factory()->count(10)
        ->for($this->debitCard)
        ->create();

        // get /debit-card-transactions/{debitCardTransaction}
        $response = $this->getJson("api/debit-card-transactions/{$transactions->first()->id}");

        // expected response code is 200
        $response->assertStatus(200);

        $response->assertJsonStructure([
            "amount",
            "currency_code"
        ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // create another user
        $user2 = User::factory()->create();

        // create anoter debit card
        $debitCard2 = DebitCard::factory()->for($user2)->create();

        // create a debit card transaction
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard2->id,
        ]);

        // get /debit-card-transactions/{debitCardTransaction}
        $response = $this->getJson("api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(403);
    }

    // Extra bonus for extra tests :)

    public function testCustomerCannotCreateADebitCardTransactionFailedValidation()
    {
        // prepare payload data
        $payload = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => "Dua ratus ribu",
            'currency_code' => 'JPY',
        ];
        // post /debit-card-transactions
        $response = $this->postJson("api/debit-card-transactions", $payload);

        // expected response code is 422
        $response->assertStatus(422);

        // check if there are errors response validation with key amount and currency code
        $response->assertJsonValidationErrors(['amount', 'currency_code']);
    }
}
