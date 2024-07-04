<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     * @throws Throwable
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        DB::beginTransaction();
        try {
            $newLoan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'terms' => $terms,
                'outstanding_amount' => $amount,
                'currency_code' => $currencyCode,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE,
            ]);

            $newLoan->scheduledRepayments()->createMany(
                $this->createPaymentScheduleList($newLoan)
            );

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $newLoan;
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        //
    }


    /**
     * Create a payment schedule list with monthly intervals.
     *
     * @param Loan $loan
     *
     * @return array
     */
    protected function createPaymentScheduleList(Loan $loan): array
    {
        $schedules = [];
        $totalDebt = $loan->amount;
        $billEachTerm = intdiv($loan->amount, $loan->terms);
        $scheduleBaseDate = CarbonImmutable::parse($loan->processed_at);

        for ($i = 1; $i <= $loan->terms; $i++) {
            // remaining debt are billed at the final payment.
            if ($i === $loan->terms) {
                $billEachTerm = $totalDebt;
            }

            $schedules[] = [
                'amount' => $billEachTerm,
                'outstanding_amount' => $billEachTerm,
                'currency_code' => $loan->currency_code,
                'due_date' => $scheduleBaseDate->addMonths($i)->format('Y-m-d'),
                'status' => ScheduledRepayment::STATUS_DUE,
            ];

            $totalDebt -= $billEachTerm;
        }

        return $schedules;
    }
}
