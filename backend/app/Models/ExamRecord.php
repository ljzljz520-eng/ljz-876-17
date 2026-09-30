<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_paper_id',
        'start_time',
        'end_time',
        'score',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'exam_paper_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'score' => 'decimal:2',
        'status' => 'string',
    ];

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS => '进行中',
        self::STATUS_SUBMITTED => '已提交',
        self::STATUS_GRADED => '已评分',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examPaper()
    {
        return $this->belongsTo(ExamPaper::class, 'exam_paper_id');
    }

    public function answers()
    {
        return $this->hasMany(ExamRecordAnswer::class, 'exam_record_id');
    }

    public function appeals()
    {
        return $this->hasMany(ScoreAppeal::class, 'exam_record_id');
    }

    /**
     * 该考试记录下的申诉汇总（供成绩单展示复核状态）
     */
    public function appealSummary(): array
    {
        $appeals = $this->appeals()->select('id', 'status', 'final_result', 'score_adjustment')->get();

        if ($appeals->isEmpty()) {
            return [
                'has_appeal' => false,
                'review_status' => null,
                'review_status_text' => '',
                'appeal_count' => 0,
                'pending_count' => 0,
            ];
        }

        $pending = $appeals->contains(fn ($a) => $a->status !== ScoreAppeal::STATUS_COMPLETED);
        $status = $pending ? ScoreAppeal::STATUS_PENDING : ScoreAppeal::STATUS_COMPLETED;

        return [
            'has_appeal' => true,
            'review_status' => $status,
            // 无论维持、加分、减分还是转教务，最终处理完成后成绩单统一显示“复核完成”
            'review_status_text' => $pending
                ? ScoreAppeal::STATUSES[ScoreAppeal::STATUS_PENDING]
                : ScoreAppeal::STATUSES[ScoreAppeal::STATUS_COMPLETED],
            'appeal_count' => $appeals->count(),
            'pending_count' => $appeals->where('status', '!=', ScoreAppeal::STATUS_COMPLETED)->count(),
        ];
    }
}
