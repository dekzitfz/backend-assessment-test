<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;
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
        
            $dueDate = \Carbon\Carbon::parse($processedAt)->addMonths(1);
            $termAmount = floor($amount / $terms); // Amount for most of the terms
            $lastTermAmount = $amount - ($termAmount * ($terms - 1)); // Adjust last term to match total amount
        
            for ($i = 1; $i <= $terms; $i++) {
                $getAmount = ($i == $terms) ? $lastTermAmount : $termAmount;
                $scheduledRepayment = [
                    'loan_id' => $loan->id,
                    'amount' => $getAmount,
                    'outstanding_amount' => $getAmount,
                    'currency_code' => $currencyCode,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'status' => ScheduledRepayment::STATUS_DUE
                ];
                ScheduledRepayment::create($scheduledRepayment);
                $dueDate = $dueDate->addMonths(1);
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
     * @return ReceivedRepayment
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

            $convertedRepayAmount = $this->convertCurrency($currencyCode, $loan->currency_code, $amount);

            $scheduledRepayments = $loan->scheduledRepayments()->orderBy("due_date")->get();

            $repaidAmount = $scheduledRepayments->where('status', ScheduledRepayment::STATUS_REPAID)->sum('amount');

            $partialScheduledRepayments = $scheduledRepayments->where('status', ScheduledRepayment::STATUS_PARTIAL);
            $partialAmount = $partialScheduledRepayments->sum('amount');
            $partialOutstandingAmount = $partialScheduledRepayments->sum('outstanding_amount');
            $partialRepaidAmount = $partialAmount - $partialOutstandingAmount;

            $finalRepaidAmount = $repaidAmount + $partialRepaidAmount;
            $finalOutstandingAmount = $loan->amount - $finalRepaidAmount - $convertedRepayAmount;

            $loan->outstanding_amount = $finalOutstandingAmount;
            $loan->status = $finalOutstandingAmount === 0 ? Loan::STATUS_REPAID : Loan::STATUS_DUE;
            $loan->save();

            foreach ($scheduledRepayments as $sr) {
                if ($sr->status === ScheduledRepayment::STATUS_REPAID) {
                    continue;
                }

                if ($convertedRepayAmount === 0) {
                    break;
                }

                $repayment = min($sr->outstanding_amount, $convertedRepayAmount);
                $convertedRepayAmount -= $repayment;

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

    protected function convertCurrency(string $from, string $to, int $amount): int
    {
        return $amount;
    }

     

}
