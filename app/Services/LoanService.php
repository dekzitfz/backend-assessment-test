<?php

namespace App\Services;

use Exception;
use Carbon\Carbon;
use App\Models\Loan;
use App\Models\User;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        DB::beginTransaction();
        try {
            //create a loan data
            $loan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'terms' => $terms,
                'outstanding_amount' => $amount,
                'currency_code' => $currencyCode,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE
            ]);

            // convert string to carbon
            $startLoan = Carbon::parse($processedAt);

            // calculate loan each mont
            $loanEachMonth = floor($amount / $terms);

            $scheduleRepayment = [];

            $i = 1;
            while ($i <= $terms)
            {
                $startLoan = $startLoan->addMonth();

                if($i === $terms)
                {
                    // dd($amount);
                    $loanEachMonth = $amount;
                }

                array_push($scheduleRepayment, [
                    'loan_id' => $loan->id,
                    'amount' => $loanEachMonth,
                    'outstanding_amount' => $loanEachMonth,
                    'currency_code' => $currencyCode,
                    'due_date' => $startLoan->format("Y-m-d"),
                   'status' => ScheduledRepayment::STATUS_DUE
                ]);

                // Log::info("looping ke: ". $i. " amountnya: " .$amount);

                // remaining amount
                $amount = $amount - $loanEachMonth;

                $i++;
            }

            // dd($scheduleRepayment);

            ScheduledRepayment::insert($scheduleRepayment);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw new Exception('Failed to create loan with errors: '.$e->getMessage());
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

            // Get all scheduled repayments.
            $scheduledRepayments = $loan->scheduledRepayments()->orderBy("due_date")->get();

            // Get total amount of scheduled repayments where status is repaid.
            $repaidAmount = $scheduledRepayments->where('status', ScheduledRepayment::STATUS_REPAID)->sum('amount');



            // Get total amount of scheduled repayments where status is partial.
            $partialScheduledRepayments = $scheduledRepayments->where('status', ScheduledRepayment::STATUS_PARTIAL);

            // Get total outstanding amount of scheduled repayments where status is repaid.
            $partialAmount = $partialScheduledRepayments->sum('amount');
            $partialOutstandingAmount = $partialScheduledRepayments->sum('outstanding_amount');

            // Calculate partial repaid amount.
            $partialRepaidAmount = $partialAmount - $partialOutstandingAmount;

            // Calculate final repaid amount and final outstanding amount.
            $finalRepaidAmount = $repaidAmount + $partialRepaidAmount;
            $finalOutstandingAmount = $loan->amount - $finalRepaidAmount - $amount;

            // dd($loan->amount, $finalRepaidAmount, $amount, $finalOutstandingAmount);

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
                if ($amount === 0) {
                    break;
                }

                // take the minimum value between outstanding amount and amount
                $repayment = min($sr->outstanding_amount, $amount);

                $amount = $amount -  $repayment;

                // Update scheduled repayment.
                $outstandingAmount = $sr->outstanding_amount - $repayment;
                Log::info("from sr: ".$sr->outstanding_amount. " Repayment: ".$repayment. " Hasil: ".($sr->outstanding_amount - $repayment));
                $sr->outstanding_amount = $outstandingAmount;
                $sr->status = $outstandingAmount === 0 ? ScheduledRepayment::STATUS_REPAID : ScheduledRepayment::STATUS_PARTIAL;
                // dd($sr);
                $sr->save();
            }

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw new Exception('Failed to repay loan with errors: '.$e->getMessage());
        }
        return $receivedPayment;
    }
}
