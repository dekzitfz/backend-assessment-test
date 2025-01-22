<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledRepayment extends Model
{
    use HasFactory;

    public const STATUS_DUE = 'due';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_REPAID = 'repaid';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'scheduled_repayments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        //
        'loan_id',
        'amount',
        'outstanding_amount',
        'currency_code',
        'due_date',
        'status'
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'disabled_at' => 'date:Y-m-d',
    ];

    protected static function booted()
    {
        static::creating(function ($scheduledRepayment) {
            if ($scheduledRepayment->outstanding_amount === null) {
                $scheduledRepayment->outstanding_amount = $scheduledRepayment->amount;
            }
            if ($scheduledRepayment->status === null) {
                $scheduledRepayment->status = self::STATUS_DUE;
            }
            if ($scheduledRepayment->status === self::STATUS_REPAID) {
                $scheduledRepayment->outstanding_amount = 0;
            }
        });
    }

    /**
     * A Scheduled Repayment belongs to a Loan
     *
     * @return BelongsTo
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }
}
