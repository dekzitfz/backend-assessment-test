<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use Carbon\Carbon;
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
            'loan_id' => fn () => Loan::factory()->create(),
            'amount' => $this->faker->randomDigit,
            'outstanding_amount' => 0,
            'currency_code' => Loan::CURRENCY_VND,
            'due_date' => Carbon::now(),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }
}
