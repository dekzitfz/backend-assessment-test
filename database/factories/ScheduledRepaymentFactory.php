<?php

namespace Database\Factories;

use App\Models\Loan;
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
        $amount = $this->faker->numberBetween(1000, 10000);

        return [
            'loan_id' => Loan::factory(),
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $this->faker->randomElement([Loan::CURRENCY_SGD, Loan::CURRENCY_VND]),
            'due_date' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];

    }

    public function configure(): ScheduledRepaymentFactory
    {
        return $this->afterMaking(function (ScheduledRepayment $sp) {
            $sp->outstanding_amount = $sp->outstanding_amount === 0 ? 0 : $sp->amount;
        });
    }

}
