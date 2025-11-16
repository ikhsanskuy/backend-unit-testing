<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;
use DB;

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
        return DB::transaction(function () use ($user, $amount, $currencyCode, $terms, $processedAt) {
            $loan = Loan::create([
                'user_id'            => $user->id,
                'amount'             => $amount,
                'terms'              => $terms,
                'outstanding_amount' => $amount,
                'currency_code'      => $currencyCode,
                'status'             => Loan::STATUS_DUE,
                'processed_at'       => $processedAt,
            ]);

            $quotient  = intdiv($amount, $terms);
            $remainder = $amount % $terms;

            $dueDate = Carbon::parse($processedAt);

            for ($i = 0; $i < $terms; $i++) {
                $installmentAmount = $quotient + ($i == $terms - 1 ? $remainder : 0);

                // Add 1 month
                $dueDate->addMonthNoOverflow();

                $loan->scheduledRepayments()->create([
                    'amount'             => $installmentAmount,
                    'outstanding_amount' => $installmentAmount,
                    'currency_code'      => $currencyCode,
                    'due_date'           => $dueDate->toDateString(),
                    'status'             => ScheduledRepayment::STATUS_DUE,
                ]);
            }

            return $loan->load('scheduledRepayments');
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
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): Loan
    {
        $remaining = $amount;

        foreach ($loan->scheduledRepayments()->orderBy('due_date')->get() as $repayment) {
            if ($remaining <= 0) {
                break;
            }

            if ($repayment->outstanding_amount <= $remaining) {
                $remaining -= $repayment->outstanding_amount;
                $repayment->outstanding_amount = 0;
                $repayment->status = ScheduledRepayment::STATUS_REPAID;
            } else {
                $repayment->outstanding_amount -= $remaining;
                $repayment->status = ScheduledRepayment::STATUS_PARTIAL;
                $remaining = 0;
            }

            $repayment->save();
        }

        $totalApplied = $amount - $remaining;

        $loan->outstanding_amount = $loan->scheduledRepayments()->sum('outstanding_amount');

        if ($loan->outstanding_amount <= 0) {
            $loan->outstanding_amount = 0;
            $loan->status = Loan::STATUS_REPAID;
        } else {
            $loan->status = Loan::STATUS_DUE;
        }
        $loan->save();

        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $totalApplied,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt,
        ]);

        return $loan;
    }
}
