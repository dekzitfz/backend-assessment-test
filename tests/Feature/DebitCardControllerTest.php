<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
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
        $debitCard = DebitCard::factory()->create([
            "user_id" => $this->user->id,
        ]);

        $put_data = [
            "is_active" => true,
        ];

        // put api/debit-cards/{debitCard}
        $response = $this->putJson("api/debit-cards/{$debitCard->id}", $put_data);

        // expected response code is 200
        $response->assertStatus(200);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            "disabled_at" => null
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            "user_id" => $this->user->id,
        ]);

        $put_data = [
            "is_active" => false,
        ];

        // put api/debit-cards/{debitCard}
        $response = $this->putJson("api/debit-cards/{$debitCard->id}", $put_data);

        // expected response code is 200
        $response->assertStatus(200);

        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            "disabled_at" => now()
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()->create([
            "user_id" => $this->user->id,
        ]);

        $put_data = [
            "is_active" => "salah",
        ];

        // put api/debit-cards/{debitCard}
        $response = $this->putJson("api/debit-cards/{$debitCard->id}", $put_data);

        // expected response code is 422
        $response->assertStatus(422);

        // check if there is a errors response validation with key is_active
        $response->assertJsonValidationErrors(['is_active']);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // create a debit card
        $debitCard = DebitCard::factory()->create([
            "user_id" => $this->user->id,
        ]);

        // delete api/debit-cards/{debitCard}
        $response = $this->deleteJson("api/debit-cards/{$debitCard->id}");

        // expected response code is 204
        $response->assertStatus(204);

        // check if debit card is soft deleted
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            "deleted_at" => now()
        ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // create a debit card
        $debitCard = DebitCard::factory()->create([
            "user_id" => $this->user->id,
        ]);

        $transactions = DebitCardTransaction::factory()->create([
            "debit_card_id" => $debitCard->id,
        ]);

        // delete api/debit-cards/{debitCard}
        $response = $this->deleteJson("api/debit-cards/{$debitCard->id}");

        // expected response code is 403
        $response->assertStatus(403);

        // check if debit card with its id still exists
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
        ]);
    }

    // Extra bonus for extra tests :)

        public function testCustomerSeeEmptyDebitCards()
    {
        // endpoint get /debit-cards
        $response = $this->getJson("api/debit-cards");

        // expected response code is 200
        $response->assertStatus(200);

        // check if actual value (from response) is greater than expected
        $this->assertEquals(0, count($response->json()));
    }

    public function testCustomerCreateDebitCardWithValidationError()
    {
        // prepare post data for creating debit card
        $post_data = [
            "type" => ""
        ];

        // post /debit-cards
        $response = $this->postJson("api/debit-cards",$post_data);

        // check if response is error 422
        $response->assertStatus(422);

        // check if there is a errors response validation with key type
        $response->assertJsonValidationErrors(['type']);
    }

    public function testCustomerCannotFoundIdOfDebitCard()
    {
        // create user
        $user2 = User::factory()->create();

        // create 3 debit cards with id from user2
        $debitCards = DebitCard::factory()->count(3)->create([
            "user_id" => $user2->id
        ]);

         // get /debit-cards/{id}
        $response = $this->getJson("api/debit-cards/99");

        // check response code is 404
        $response->assertStatus(404);
    }

    public function testCustomerCanNotDeleteADebitCardBecauseIdNotFound()
    {
        // create a debit card
        $debitCard = DebitCard::factory()->create([
            "user_id" => $this->user->id,
        ]);

        $id = $debitCard->id + 11;

        // delete api/debit-cards/{debitCard}
        $response = $this->deleteJson("api/debit-cards/{$id}");

        // expected response code is 204
        $response->assertStatus(404);
    }
}
