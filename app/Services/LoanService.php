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
        DB::beginTransaction();
        try {
            $loan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'terms' => $terms,
                'outstanding_amount' => $amount,
                'currency_code' => $currencyCode,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE,
            ]);

            $this->createScheduledRepayments($loan);

            DB::commit();
            return $loan;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
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
            $scheduledRepayment = $loan->scheduledRepayments()->where('status', ScheduledRepayment::STATUS_DUE)->orderBy('due_date')->firstOrFail();

            if ($amount >= $scheduledRepayment->outstanding_amount) {
                // Repay fully
                $scheduledRepayment->update(['status' => ScheduledRepayment::STATUS_REPAID, 'outstanding_amount' => 0]);
                $received = ReceivedRepayment::create([
                    'loan_id' => $loan->id,
                    'amount' => $amount,
                    'currency_code' => $currencyCode,
                    'received_at' => $receivedAt
                ]);
                $loan->update(['outstanding_amount' => $loan->amount - $amount]);
            } else {
                // Repay partially
                $scheduledRepayment->update(['outstanding_amount' => $scheduledRepayment->outstanding_amount - $amount]);
                $received = ReceivedRepayment::create([
                    'loan_id' => $loan->id,
                    'amount' => $amount,
                    'currency_code' => $currencyCode,
                    'received_at' => $receivedAt
                ]);
                $loan->update(['outstanding_amount' => $loan->amount - $amount]);
                $scheduledRepayment->update(['status' => ScheduledRepayment::STATUS_PARTIAL]);
            }

            DB::commit();
            return $received;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Create Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @return void
     */
    protected function createScheduledRepayments(Loan $loan): void
    {
        $scheduled = [];
        $terms = $loan->terms;
        $totalAmount = $loan->amount;
        $amountPerTerm = intdiv($loan->amount, $terms);
        $dueDate = $loan->processed_at;

        for ($i = 0; $i < $terms; $i++) {
            $dueDate = Carbon::parse($dueDate)->addMonth()->format('Y-m-d');

            if ($i == $terms - 1) {
                $amountPerTerm = round($totalAmount);
            }

            array_push($scheduled, [
                'loan_id' => $loan->id,
                'amount' => $amountPerTerm,
                'outstanding_amount' => $amountPerTerm,
                'currency_code' => $loan->currency_code,
                'due_date' => $dueDate,
                'status' => ScheduledRepayment::STATUS_DUE,
            ]);

            $totalAmount = $totalAmount - $amountPerTerm;
        }

        ScheduledRepayment::insert($scheduled);
    }
}
