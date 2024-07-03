<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;

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
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE,
        ]);

        // Calculate equal installment amount
        $installmentAmount = intval($amount / $terms);
        $remainder = $amount % $terms;

        // Create scheduled repayments
        $dueDate = Carbon::parse($processedAt)->addMonths(1); // Start from the month after processing

        for ($i = 0; $i < $terms; $i++) {
            // Adjust the amount for the last installment to cover the remainder
            if ($i === $terms - 1) {
                $installmentAmount += $remainder;
            }

            ScheduledRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $installmentAmount,
                'outstanding_amount' => $installmentAmount,
                'currency_code' => $currencyCode,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => ScheduledRepayment::STATUS_DUE,
            ]);

            $dueDate->addMonths(1);
        }

        return $loan;
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
        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt,
        ]);

        $outstandingAmount = $amount;

        foreach ($loan->scheduledRepayments()->where('status', '!=', ScheduledRepayment::STATUS_REPAID)->orderBy('due_date')->get() as $repayment) {
            if ($outstandingAmount <= 0) {
                break;
            }

            if ($outstandingAmount >= $repayment->outstanding_amount) {
                $outstandingAmount -= $repayment->outstanding_amount;
                $repayment->update([
                    'outstanding_amount' => 0,
                    'status' => ScheduledRepayment::STATUS_REPAID,
                ]);
            } else {
                $repayment->update([
                    'outstanding_amount' => $repayment->outstanding_amount - $outstandingAmount,
                    'status' => ScheduledRepayment::STATUS_PARTIAL,
                ]);
                $outstandingAmount = 0;
            }
        }

        $loanOutstandingAmount = $loan->scheduledRepayments()->sum('outstanding_amount');
        $loan->update([
            'outstanding_amount' => $loanOutstandingAmount,
            'status' => $loanOutstandingAmount == 0 ? Loan::STATUS_REPAID : Loan::STATUS_DUE,
        ]);

        return $loan;
    }
}
