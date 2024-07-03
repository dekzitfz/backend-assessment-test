<?php

namespace Database\Factories;

use App\Models\ScheduledRepayment;
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
            // TODO: Complete factory
            'loan_id' => Loan::factory(),
            'amount' => $this->faker->numberBetween(1000, 5000),
            'outstanding_amount' => $this->faker->numberBetween(1000, 5000),
            'currency_code' => $this->faker->randomElement([Loan::CURRENCY_SGD, Loan::CURRENCY_VND]),
            'due_date' => $this->faker->date(),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }
}
