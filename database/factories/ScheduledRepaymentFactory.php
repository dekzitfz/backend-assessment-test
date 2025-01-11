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
            'load_id' => fn () => Loan::factory(),
            'amount' => $this->faker->randomNumber(),
            'terms' => $this->faker->randomDigitNotNull(),
            'outstanding_amount' => $this->faker->randomNumber(),
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => $this->faker->date(),
            'status' => Loan::STATUS_DUE,
        ];
    }
}
