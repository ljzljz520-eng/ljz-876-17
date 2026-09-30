<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreAppealReview extends Model
{
    protected $fillable = [
        'score_appeal_id',
        'handler_id',
        'handler_role',
        'action',
        'result',
        'score_adjustment',
        'score_before',
        'score_after',
        'comment',
        'assigned_from',
        'assigned_to',
    ];

    protected $casts = [
        'score_appeal_id' => 'integer',
        'handler_id' => 'integer',
        'assigned_from' => 'integer',
        'assigned_to' => 'integer',
        'score_adjustment' => 'decimal:2',
        'score_before' => 'decimal:2',
        'score_after' => 'decimal:2',
    ];

    public const ACTION_SUBMIT = 'submit';
    public const ACTION_TRANSFER = 'transfer';
    public const ACTION_UPHELD = 'upheld';
    public const ACTION_ADD_SCORE = 'add_score';
    public const ACTION_DEDUCT_SCORE = 'deduct_score';

    public const ACTIONS = [
        self::ACTION_SUBMIT => '发起申诉',
        self::ACTION_TRANSFER => '转交教务',
        self::ACTION_UPHELD => '维持原判',
        self::ACTION_ADD_SCORE => '加分',
        self::ACTION_DEDUCT_SCORE => '减分',
    ];

    public function appeal()
    {
        return $this->belongsTo(ScoreAppeal::class, 'score_appeal_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'assigned_from');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
