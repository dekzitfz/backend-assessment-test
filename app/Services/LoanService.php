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
     * @throws Throwable
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        DB::beginTransaction();
        try {
            // Record received repayment.
            $receivedPayment = new ReceivedRepayment([
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'received_at' => $receivedAt,
            ]);
            $loan->receivedPayments()->save($receivedPayment);

            // Convert repay currency to Loan currency.
            $convertedRepayAmount = $this->convertCurrency($currencyCode, $loan->currency_code, $amount);

            // Get all scheduled repayments.
            $scheduledRepayments = $loan->scheduledRepayments()->orderBy("due_date")->get();

            // Get total amount of scheduled repayments where status is repaid.
            $repaidAmount = $scheduledRepayments->where('status', ScheduledRepayment::STATUS_REPAID)->sum('amount');

            // Get total amount of scheduled repayments where status is partial.
            // Get total outstanding amount of scheduled repayments where status is repaid.
            // Calculate partial repaid amount.
            $partialScheduledRepayments = $scheduledRepayments->where('status', ScheduledRepayment::STATUS_PARTIAL);
            $partialAmount = $partialScheduledRepayments->sum('amount');
            $partialOutstandingAmount = $partialScheduledRepayments->sum('outstanding_amount');
            $partialRepaidAmount = $partialAmount - $partialOutstandingAmount;

            // Calculate final repaid amount and final outstanding amount.
            $finalRepaidAmount = $repaidAmount + $partialRepaidAmount;
            $finalOutstandingAmount = $loan->amount - $finalRepaidAmount - $convertedRepayAmount;

            // Update loan.
            $loan->outstanding_amount = $finalOutstandingAmount;
            $loan->status = $finalOutstandingAmount === 0 ? Loan::STATUS_REPAID : Loan::STATUS_DUE;
            $loan->save();

            // Update scheduled repayments where status is not repaid.
            foreach ($scheduledRepayments as $sr) {
                // Skip when scheduled repayment is already repaid.
                if ($sr->status === ScheduledRepayment::STATUS_REPAID) {
                    continue;
                }

                // Stop when converted amount has been used up.
                if ($convertedRepayAmount === 0) {
                    break;
                }

                // Calculate repayment amount to be make based on current outstanding amount vs converted amount left.
                $repayment = min($sr->outstanding_amount, $convertedRepayAmount);
                $convertedRepayAmount -= $repayment;

                // Update scheduled repayment.
                $outstandingAmount = $sr->outstanding_amount - $repayment;
                $sr->outstanding_amount = $outstandingAmount;
                $sr->status = $outstandingAmount === 0 ? ScheduledRepayment::STATUS_REPAID : ScheduledRepayment::STATUS_PARTIAL;
                $sr->save();
            }

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $receivedPayment;
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

    /**
     * Convert currency.
     *
     * @param string $from
     * @param string $to
     *
     * @param int $amount
     * @return int
     */
    protected function convertCurrency(string $from, string $to, int $amount): int
    {
        // TODO: Implement currency conversion.
        return $amount;
    }
}
