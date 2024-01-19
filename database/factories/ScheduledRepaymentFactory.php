<?php

namespace Database\Factories;

use App\Models\ScheduledRepayment;
use App\Models\Loan;
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
            'loan_id' => fn() => Loan::factory()->create(),
            'amount' => $this->faker->randomNumber(),
            'outstanding_amount' => 0,
            'currency_code' => Loan::CURRENCY_SGD,
            'due_date' => $this->faker->dateTime,
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }
}
