<?php

namespace Database\Factories;

use App\Models\{Loan, ScheduledRepayment};
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScheduledRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'loan_id' => fn () => Loan::factory(),
            'amount' => $this->faker->randomNumber(),
            'outstanding_amount' => function (array $attributes) {
                return $attributes['amount'];
            },
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => $this->faker->date(),
            'status' => Loan::STATUS_DUE,
        ];
    }
}
