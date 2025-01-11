<?php

namespace App\Services;

use App\Models\{Loan, ReceivedRepayment, ScheduledRepayment, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Log};

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
        try {
            DB::begintransaction();

            // insert receive payment
            $receive_payment = ReceivedRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'received_at' => Carbon::parse($receivedAt)->format('Y-m-d'),
            ]);

            // check ScedulerPayment
            $schedule_payments = ScheduledRepayment::where('loan_id', $loan->id)->get();

            // subtract the schedule payment
            $remaining_amount_payment = $amount;
            foreach ($schedule_payments as $schedule_payment) {
                // check if already repaid
                if ($schedule_payment->status == ScheduledRepayment::STATUS_REPAID) {
                    continue;
                }

                $outstanding_amount = $schedule_payment->outstanding_amount;

                $remaining_amount_payment = ($remaining_amount_payment - $outstanding_amount);

                $status_payment = self::statusPayment($remaining_amount_payment);

                $update_payment = [
                    'status' => $status_payment,
                    'outstanding_amount' => ($remaining_amount_payment > 0) ? 0 : abs($remaining_amount_payment),
                    'updated_at' => Carbon::now(),
                ];

                ScheduledRepayment::where('id', $schedule_payment->id)
                    ->where('loan_id', $loan->id) // Loan
                    ->update($update_payment);
                    
                // there is no amount left
                if ($remaining_amount_payment <= 0) {
                    break;
                }
            }

            // get lastest loan outsanding amount 
            // since every request keep get same outstanding amount
            $updated_loan = Loan::where('id', $loan->id)->first();
            $remaining_loan = abs($amount) - abs($updated_loan->outstanding_amount);
            $status_loan = self::statusLoan($remaining_loan);
            $update_loan = [
                'outstanding_amount' => ($remaining_loan >= 0) ? 0 : abs($remaining_loan),
                'status' => $status_loan,
            ];
            Loan::where('id', $loan->id)->update($update_loan);

            DB::commit();

            return $receive_payment;
        } catch (\Throwable $th) {
            DB::rollBack();

            Log::error("Loan Service Function repayLoan");
            Log::error($th);
        }
    }

    protected function statusPayment($remaining_amount_payment)
    {
        if ($remaining_amount_payment >= 0) {
            return ScheduledRepayment::STATUS_REPAID;
        }

        if ($remaining_amount_payment < 0) {
            return ScheduledRepayment::STATUS_PARTIAL;
        }
    }

    protected function statusLoan($remaining_loan)
    {
        if ($remaining_loan >= 0) {
            return Loan::STATUS_REPAID;
        }

        if ($remaining_loan < 0) {
            return Loan::STATUS_DUE;
        }
    }
}
