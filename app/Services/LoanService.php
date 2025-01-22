<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;
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
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        //
        return DB::transaction(function () use ($user, $amount, $currencyCode, $terms, $processedAt) {
            $loan = $user->loans()->create([
                'amount' => $amount,
                'outstanding_amount' => $amount,
                'currency_code' => $currencyCode,
                'terms' => $terms,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE
            ]);

            $scheduledRepayments = [];
            $scheduledRepaymentsAmount = $amount / $terms;
            $processedDate = Carbon::parse($processedAt);
            for ($i = 1; $i <= $terms; $i++) {
                $dueDate = $processedDate->copy()->addMonth($i)->format('Y-m-d');
                $amountToRepay = ($i === $terms) ? ceil($scheduledRepaymentsAmount) : floor($scheduledRepaymentsAmount);

                array_push($scheduledRepayments, [
                    'loan_id' => $loan->id,
                    'amount' => $amountToRepay,
                    'outstanding_amount' => $amountToRepay,
                    'currency_code' => $currencyCode,
                    'due_date' => $dueDate,
                    'status' => ScheduledRepayment::STATUS_DUE
                ]);
            }

            ScheduledRepayment::insert($scheduledRepayments);

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
        //
        return DB::transaction(function () use ($loan, $amount, $currencyCode, $receivedAt) {
            $totalRepaid = $loan->scheduledRepayments()->where([
                'status' => Loan::STATUS_REPAID
            ])->sum('amount');

            $loanOutstandingAmount = $loan->amount - $amount - $totalRepaid;

            $loan->update([
                'outstanding_amount' => $loanOutstandingAmount,
                'status' => $loanOutstandingAmount == 0 ? ScheduledRepayment::STATUS_REPAID : ScheduledRepayment::STATUS_DUE
            ]);

            $scheduledRepayments = $loan->scheduledRepayments()->where([
                'due_date' => $receivedAt,
                'currency_code' => $currencyCode
            ]);
            
            $outstandingAmount = $scheduledRepayments->first()->outstanding_amount - $amount;
            if($outstandingAmount < 0){
                $scheduledRepayments->update([
                    'outstanding_amount' => 0,
                    'status' => ScheduledRepayment::STATUS_REPAID
                ]);

                $nextDueDate = Carbon::parse($receivedAt)->addMonth(1)->format('Y-m-d');
                $this->repayLoan($loan, abs($outstandingAmount), $currencyCode, $nextDueDate);
            }else {
                $status = ScheduledRepayment::STATUS_DUE;
                if($outstandingAmount == 0){
                    $status = ScheduledRepayment::STATUS_REPAID;
                }else if($outstandingAmount < $scheduledRepayments->first()->amount){
                    $status = ScheduledRepayment::STATUS_PARTIAL;
                }
                $scheduledRepayments->update([
                    'outstanding_amount' => $outstandingAmount,
                    'status' => $status
                ]);
            }

            $loan->receivedRepayments()->create([
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'received_at' => $receivedAt
            ]);

            return $loan;
        });
    }
}
