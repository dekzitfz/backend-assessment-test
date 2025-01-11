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
