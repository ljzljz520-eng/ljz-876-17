<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreAppealReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'score_appeal_id',
        'reviewer_id',
        'reviewer_role',
        'action',
        'comment',
        'score_adjustment',
        'score_after',
    ];

    protected $casts = [
        'score_appeal_id' => 'integer',
        'reviewer_id' => 'integer',
        'score_adjustment' => 'decimal:2',
        'score_after' => 'decimal:2',
    ];

    public const ACTION_MAINTAIN = 'maintain';
    public const ACTION_ADD = 'add';
    public const ACTION_DEDUCT = 'deduct';
    public const ACTION_TRANSFER = 'transfer';

    public const ACTIONS = [
        self::ACTION_MAINTAIN => '维持',
        self::ACTION_ADD => '加分',
        self::ACTION_DEDUCT => '减分',
        self::ACTION_TRANSFER => '转教务',
    ];

    public function appeal()
    {
        return $this->belongsTo(ScoreAppeal::class, 'score_appeal_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
