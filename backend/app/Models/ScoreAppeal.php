<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreAppeal extends Model
{
    protected $fillable = [
        'exam_record_id',
        'student_id',
        'question_id',
        'type',
        'reason',
        'status',
        'assigned_to',
        'submitted_at',
        'closed_at',
    ];

    protected $casts = [
        'exam_record_id' => 'integer',
        'student_id' => 'integer',
        'question_id' => 'integer',
        'assigned_to' => 'integer',
        'submitted_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public const TYPE_SCORE = 'score';
    public const TYPE_JUDGE = 'judge';
    public const TYPE_ABNORMAL = 'abnormal';

    public const TYPES = [
        self::TYPE_SCORE => '分数申诉',
        self::TYPE_JUDGE => '判题异议',
        self::TYPE_ABNORMAL => '异常标记',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_PENDING => '待复核',
        self::STATUS_TRANSFERRED => '已转教务',
        self::STATUS_CLOSED => '复核完成',
    ];

    public function examRecord()
    {
        return $this->belongsTo(ExamRecord::class, 'exam_record_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviews()
    {
        return $this->hasMany(ScoreAppealReview::class, 'score_appeal_id')->orderBy('id');
    }

    public function evidences()
    {
        return $this->hasMany(ScoreAppealEvidence::class, 'score_appeal_id')->orderBy('id');
    }
}
