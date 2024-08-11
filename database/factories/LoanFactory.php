<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(1000, 2000);

        return [
            // TODO: Complete factory
            'user_id' => fn() => User::factory()->create(),
            'terms' => $this->faker->randomNumber(1),
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => Loan::CURRENCY_SGD,
            'processed_at' => $this->faker->dateTimeThisYear(),
            'status' => Loan::STATUS_DUE,
        ];
    }
}
