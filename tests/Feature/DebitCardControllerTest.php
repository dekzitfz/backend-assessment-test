<?php

namespace Tests\Feature;

use App\Models\{DebitCard, DebitCardTransaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Carbon\Carbon;

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
        // create 5 user Debit Card
        DebitCard::factory()->count(5)->create([
            'user_id' => $this->user->id
        ]);

        // get /debit-cards
        $response = $this->get('api/debit-cards');

        // response code 200
        $response->assertStatus(200);

        // 0 also can see list debit
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // create a user
        $other_user = User::factory()->create();

        // create 5 other customer Debit Card
        DebitCard::factory()->count(5)->create([
            'user_id' => $other_user->id
        ]);

        // Debit Card data searching using $this->user
        // get /debit-cards 
        $response = $this->get('api/debit-cards');

        // response code 200
        $response->assertStatus(200);

        // must 0,
        // since we login using $this->user not other user
        $this->assertEquals(0, count($response->json()));
    }

    public function testCustomerCanCreateADebitCard()
    {
        $post_data = [
            'type' => 'Visa',
        ];

        // post /debit-cards
        $response = $this->post('api/debit-cards', $post_data);

        // response code 201
        $response->assertStatus(201);

        // check data response with database
        $data_resp = $response->json();

        //check debit card number & type input
        $where_clause = array_merge($post_data, ['number' => $data_resp['number']]);
        $debit_card_user = DebitCard::where($where_clause)->first();

        // check if debit card DB same with current user id Login
        $is_debit_card_number_equal_to_user_id = $debit_card_user->user_id == $this->user->id;

        $this->assertTrue($is_debit_card_number_equal_to_user_id);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // create user debit card
        $create_debit_card = DebitCard::factory()->create(['user_id' => $this->user->id]);

        // get api/debit-cards/{debitCard}
        $response = $this->get("api/debit-cards/$create_debit_card->id");

        // response code 200
        $response->assertStatus(200);

        //check response with DB
        $response->assertJson([
            "id" => $create_debit_card->id,
            "number" => $create_debit_card->number,
            "type" => $create_debit_card->type,
            "expiration_date" => Carbon::parse($create_debit_card->expiration_date)->format('Y-m-d H:i:s'),
            "is_active" => $create_debit_card->is_active,
        ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        //create other user
        $other_user = User::factory()->create();

        // create debit card other user
        $create_debit_card = DebitCard::factory()->create(['user_id' => $other_user->id]);

        // get api/debit-cards/{debitCard}
        $response = $this->get("api/debit-cards/$create_debit_card->id");

        $response->assertForbidden();
    }

    public function testCustomerCanActivateADebitCard()
    {
        // create user debit card
        $create_debit_card = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        // request data
        $put_data = [
            'is_active' => true
        ];

        // put api/debit-cards/{debitCard}
        $response = $this->put("api/debit-cards/$create_debit_card->id", $put_data);

        // response code 200
        $response->assertStatus(200);

        //check debit card number & type input
        $where_clause = [
            'user_id' => $create_debit_card->user_id,
            'number' => $create_debit_card->number,
        ];
        $debit_card_user = DebitCard::where($where_clause)->first();

        //check Database if debit card active
        $is_active_debit_card = is_null($debit_card_user->disabled_at);

        $this->assertTrue($is_active_debit_card);

        // check data response with database
        $response->assertJson([
            "id" => $create_debit_card->id,
            "number" => $create_debit_card->number,
            "type" => $create_debit_card->type,
            "expiration_date" => Carbon::parse($create_debit_card->expiration_date)->format('Y-m-d H:i:s'),
            "is_active" => $put_data['is_active'],
        ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // create user debit card
        $create_debit_card = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        // request data
        $put_data = [
            'is_active' => false
        ];

        // put api/debit-cards/{debitCard}
        $response = $this->put("api/debit-cards/$create_debit_card->id", $put_data);

        // response code 200
        $response->assertStatus(200);

        //check debit card number & type input
        $where_clause = [
            'user_id' => $create_debit_card->user_id,
            'number' => $create_debit_card->number,
        ];
        $debit_card_user = DebitCard::where($where_clause)->first();

        //check Database if debit card deactive
        $is_deactive_debit_card = $debit_card_user->disabled_at;

        $this->assertNotNull($is_deactive_debit_card);

        // check data response with database
        $response->assertJson([
            "id" => $create_debit_card->id,
            "number" => $create_debit_card->number,
            "type" => $create_debit_card->type,
            "expiration_date" => Carbon::parse($create_debit_card->expiration_date)->format('Y-m-d H:i:s'),
            "is_active" => $put_data['is_active'],
        ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // create user debit card
        $create_debit_card = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);

        // request data
        $put_data = [];

        // put api/debit-cards/{debitCard}
        $response = $this->put("api/debit-cards/$create_debit_card->id", $put_data);

        // response code 400
        $response->assertStatus(400);

        // check validation
        $response->assertJsonValidationErrors(['is_active']);
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
