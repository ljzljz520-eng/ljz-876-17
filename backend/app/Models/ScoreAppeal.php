<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreAppeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_record_id',
        'question_id',
        'student_id',
        'appeal_type',
        'reason',
        'status',
        'original_score',
        'final_score',
        'score_adjustment',
        'final_result',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'question_id' => 'integer',
        'student_id' => 'integer',
        'original_score' => 'decimal:2',
        'final_score' => 'decimal:2',
        'score_adjustment' => 'decimal:2',
        'closed_by' => 'integer',
        'closed_at' => 'datetime',
    ];

    // 申诉类型
    public const TYPE_SCORE = 'score';           // 对分数有异议
    public const TYPE_SCORING = 'scoring';       // 对判题有异议
    public const TYPE_ABNORMAL = 'abnormal';     // 异常标记

    public const TYPES = [
        self::TYPE_SCORE => '分数异议',
        self::TYPE_SCORING => '判题异议',
        self::TYPE_ABNORMAL => '异常标记',
    ];

    // 申诉状态
    public const STATUS_PENDING = 'pending';           // 待老师复核
    public const STATUS_TO_ACADEMIC = 'to_academic';   // 已转教务(教务待处理)
    public const STATUS_COMPLETED = 'completed';       // 复核完成(终态)

    public const STATUSES = [
        self::STATUS_PENDING => '待复核',
        self::STATUS_TO_ACADEMIC => '已转教务',
        self::STATUS_COMPLETED => '复核完成',
    ];

    // 复核动作 / 最终结论
    public const RESULT_MAINTAIN = 'maintain';
    public const RESULT_ADD = 'add';
    public const RESULT_DEDUCT = 'deduct';
    public const RESULT_TRANSFER = 'transfer';

    public const RESULTS = [
        self::RESULT_MAINTAIN => '维持原判',
        self::RESULT_ADD => '加分',
        self::RESULT_DEDUCT => '减分',
        self::RESULT_TRANSFER => '转教务',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function evidences()
    {
        return $this->hasMany(ScoreAppealEvidence::class, 'score_appeal_id')->orderBy('id');
    }

    public function reviews()
    {
        return $this->hasMany(ScoreAppealReview::class, 'score_appeal_id')->orderBy('id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
