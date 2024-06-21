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
        $amount = $this->faker->numberBetween(1000, 100000);

        return [
            'loan_id' => Loan::factory(),
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $this->faker->randomElement([Loan::CURRENCY_SGD, Loan::CURRENCY_VND]),
            'due_date' => $this->faker->dateTimeThisYear(),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }

    /**
     * Configure the model factory.
     *
     * @return $this
     */
    public function configure(): self
    {
        return $this->afterMaking(function (ScheduledRepayment $scheduledRepayment) {
            $scheduledRepayment->outstanding_amount = $scheduledRepayment->amount;
        });
    }
}
