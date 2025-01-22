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
        //
    }
}
