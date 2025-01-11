<?php

namespace App\Services;

use App\Models\{Loan, ReceivedRepayment, ScheduledRepayment, User};
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

        // create list of loan for schedule repayment
        $long_terms = $terms;
        $start_loan = Carbon::parse($processedAt);
        $loan_each_month = floor($amount / $long_terms);
        $remaining_amount_in_last_month = $amount - ($loan_each_month * ($long_terms - 1));
        $insert_schedule_repayment = [];

        for ($i = 1; $i <= $long_terms; $i++) {
            $start_loan = $start_loan->addMonth(1);

            if( $i == $long_terms){
                $loan_each_month = $remaining_amount_in_last_month;
            }

            $insert_schedule_repayment[] = [
                'loan_id' => $loan->id,
                'amount' => $loan_each_month,
                'outstanding_amount' => $loan_each_month,
                'currency_code' => $currencyCode,
                'due_date' => Carbon::parse($start_loan)->format('Y-m-d'),
                'status' => ScheduledRepayment::STATUS_DUE,
            ];
        }
        ScheduledRepayment::insert($insert_schedule_repayment);
        
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
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        //
    }
}
