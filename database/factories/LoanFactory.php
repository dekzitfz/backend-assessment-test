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
        $terms = $this->faker->numberBetween(3, 12);

        $baseAmount = $this->faker->numberBetween(1000, 10000);
        $amount = $baseAmount * $terms;

        return [
            'user_id' => User::factory(),
            'terms' => $terms,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $this->faker->randomElement([Loan::CURRENCY_SGD, Loan::CURRENCY_VND]),
            'processed_at' => now()->format('Y-m-d'),
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
