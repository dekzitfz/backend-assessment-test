<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DebitCard;
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
        DebitCard::factory()->count(10)->create(['user_id' => $this->user->id]);
        $response = $this->getJson('/api/debit-cards');
        $response->assertStatus(200);

        //return data should be more than 0 but it could be less than 10 because on the factory some debit cards might be non active
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $otherUser = User::factory()->create();

        DebitCard::factory()->count(10)->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertOk();
        $response->assertJsonCount(0); // return data should be 0
    }

    public function testCustomerCanCreateADebitCard()
    {
        $data = [
            'type' => 'MasterCard',
        ];

        $response = $this->postJson('/api/debit-cards', $data);
        $response->assertCreated();
        $this->assertDatabaseHas('debit_cards', $data + ['user_id' => $this->user->id]); // check if theres debit cards with this user id
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
    }

    // Extra bonus for extra tests :)
}
