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
        return [
            'user_id' => fn () => User::factory()->create(),
            'amount' => $this->faker->randomNumber(),
            'terms' => $this->faker->randomDigitNotNull(),
            'outstanding_amount' => $this->faker->randomNumber(),
            'currency_code' => Loan::CURRENCY_VND,
            'processed_at' => $this->faker->date(),
            'status' => Loan::STATUS_DUE,
        ];
    }

    public function configure(): LoanFactory
    {
        return $this->afterMaking(function (Loan $loan) {
            $loan->outstanding_amount = $loan->outstanding_amount === 0 ? 0 : $loan->amount;
        });
    }
}
