<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DebitCard;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        // create 10 debit cards with same user id
        $debitCards = DebitCard::factory()->count(10)
        ->create([
            "user_id" => $this->user->id
        ]);

        // endpoint get /debit-cards
        $response = $this->getJson("api/debit-cards");

        // expected response code is 200
        $response->assertStatus(200);

        // check if actual value (from response) is greater than expected
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // create user
        $user2 = User::factory()->create();

        // create 3 debit cards with id from user2
        $debitCards = DebitCard::factory()->count(3)->create([
            "user_id" => $user2->id
        ]);

         // get /debit-cards
        $response = $this->getJson("api/debit-cards");

        // check response code is 200
        $response->assertStatus(200);

        // total should be 0, cause we login as another user
        $this->assertEquals(0, count($response->json()));
    }

    public function testCustomerCanCreateADebitCard()
    {
        // prepare post data for creating debit card
        $post_data = [
            "type" => "gpn"
        ];

        // post /debit-cards
        $response = $this->postJson("api/debit-cards",$post_data);

        // check if data is created successfully
        $response->assertStatus(201);

        $this->assertDatabaseHas('debit_cards', $post_data + [
            'user_id' => $this->user->id,
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // create debit card for logged in user
        $debitCard = DebitCard::factory()->create(["user_id" => $this->user->id]);

        // get api/debit-cards/{debitCard}
        $response = $this->getJson("api/debit-cards/{$debitCard->id}");

        // check if response is ok
        $response->assertStatus(200);

        // check if response contains expected fields
        $response->assertJson([
            'id' => $debitCard->id,
            'number' => $debitCard->number,
            'type' => $debitCard->type,
            'is_active' => $debitCard->is_active,
        ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // create user
        $user2 = User::factory()->create();

        // create 3 debit cards with id from user2
        $debitCards = DebitCard::factory()->count(3)->create([
            "user_id" => $user2->id
        ]);

        // get api/debit-cards/{debitCard}
        $response = $this->getJson("api/debit-cards/".$debitCards[0]->id);

        // dd($response->json());

        // expected response forbidden = 403
        $response->assertStatus(403);
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
