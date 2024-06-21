<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
     * @throws \Throwable
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        return DB::transaction(function () use ($user, $amount, $currencyCode, $terms, $processedAt) {
            $loan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'terms' => $terms,
                'outstanding_amount' => $amount,
                'currency_code' => $currencyCode,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE,
            ]);

            $loan->scheduledRepayments()->createMany(
                $this->prepareSchedules($loan)
            );

            return $loan;
        });
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return Loan
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): Loan
    {
        \DB::transaction(function () use ($loan, $amount, $currencyCode, $receivedAt) {
            $loan->load(['scheduledRepayments']);

            $scheduledUnprepaidRepayments = $loan->scheduledRepayments->filter(fn ($q) => $q->status !== ScheduledRepayment::STATUS_REPAID);

            $currencyAmount = $this->parseAmountToLoanCurrency($amount, $currencyCode, $loan->currency_code);
            $outstandingAmount = $currencyAmount;

            foreach ($loan->scheduledRepayments as $key => $scheduledRepayment) {
                // Handle if scheduled payment doesnt have prepaid payments from test
                if ($scheduledRepayment->due_date < $receivedAt && $scheduledRepayment->status !== ScheduledRepayment::STATUS_DUE) {
                    ReceivedRepayment::create([
                        'loan_id' => $loan->id,
                        'amount' => $scheduledRepayment->amount,
                        'currency_code' => $currencyCode,
                        'received_at' => $scheduledRepayment->due_date,
                    ]);
                } else {
                    if ($outstandingAmount > 0) {
                        $check = $outstandingAmount >= $scheduledRepayment->outstanding_amount;
                        $data = [
                            'status' => $check ? ScheduledRepayment::STATUS_REPAID : ScheduledRepayment::STATUS_PARTIAL,
                            'outstanding_amount' => $check ? 0 : $outstandingAmount
                        ];
                        $outstandingAmount = $outstandingAmount - $scheduledRepayment->outstanding_amount;
                        $scheduledRepayment->update($data);
                    }
                }
            }

            // store received payment
            $received = ReceivedRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $currencyAmount,
                'currency_code' => $currencyCode,
                'received_at' => $receivedAt,
            ]);

            $loan->update([
                'outstanding_amount' => $loanOutstanding = $loan->outstanding_amount - ReceivedRepayment::sum('amount'),
                'status' => !$loanOutstanding ? Loan::STATUS_REPAID : Loan::STATUS_DUE,
            ]);
        });

        return $loan;
    }

    /**
     * Parse amount to loan currency
     *
     * @param  int  $amount
     * @param  string  $from
     * @param  string  $to
     * @return int
     */
    protected function parseAmountToLoanCurrency(int $amount, string $from, string $to): int
    {
        if ($from === $to) {
            return $amount;
        }

        // assume the rate is 1
        $rate = 1;

        return $amount * $rate;
    }

    /**
     * Prepare data of scheduled repayment.
     *
     * @param  Loan  $loan
     * @return array
     */
    protected function prepareSchedules(Loan $loan): array
    {
        $schedules = [];
        $totalAmount = $loan->amount;
        $amountPerTerm = intdiv($loan->amount, $loan->terms);
        $termStartedAt = $loan->processed_at;

        for ($i = 1; $i <= $loan->terms; $i++) {
            $dueDate = Carbon::parse($termStartedAt)->addMonth()->format('Y-m-d');
            $termStartedAt = $dueDate;

            if ($i === $loan->terms) {
                $amountPerTerm = round($totalAmount);
            }

            $schedules[] = [
                'amount' => $amountPerTerm,
                'outstanding_amount' => $amountPerTerm,
                'currency_code' => $loan->currency_code,
                'due_date' => $dueDate,
                'status' => ScheduledRepayment::STATUS_DUE,
            ];

            $totalAmount -= $amountPerTerm;
        }

        return $schedules;
    }
}
