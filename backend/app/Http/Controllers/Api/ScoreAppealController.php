<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamRecord;
use App\Models\ScoreAppeal;
use App\Models\ScoreAppealEvidence;
use App\Models\ScoreAppealReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ScoreAppealController extends Controller
{
    /** 允许上传的证据文件类型 */
    private const ALLOWED_MIMES = 'jpg,jpeg,png,pdf,doc,docx,txt,zip';
    private const MAX_FILES = 5;
    private const MAX_FILE_KB = 10240;

    /* ===================== 学生端 ===================== */

    /**
     * 学生提交成绩申诉：选择题目、写原因并上传证据
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->isStudent()) {
            return response()->json(['message' => '仅学生可提交成绩申诉'], 403);
        }

        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'question_id' => 'nullable|exists:questions,id',
            'appeal_type' => 'required|in:' . implode(',', array_keys(ScoreAppeal::TYPES)),
            'reason' => 'required|string|min:5|max:1000',
            'evidences' => 'nullable|array|max:' . self::MAX_FILES,
            'evidences.*' => 'file|mimes:' . self::ALLOWED_MIMES . '|max:' . self::MAX_FILE_KB,
        ], [
            'reason.required' => '请填写申诉原因',
            'reason.min' => '申诉原因至少 5 个字符',
            'evidences.max' => '证据文件最多上传 ' . self::MAX_FILES . ' 个',
            'evidences.*.mimes' => '证据仅支持 jpg/jpeg/png/pdf/doc/docx/txt/zip 格式',
            'evidences.*.max' => '单个证据文件不能超过 10MB',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var ExamRecord $record */
        $record = ExamRecord::with('examPaper')->find($request->exam_record_id);

        if ($record->user_id !== $user->id) {
            return response()->json(['message' => '无权对他人的成绩提交申诉'], 403);
        }

        if ($record->status !== ExamRecord::STATUS_GRADED) {
            return response()->json(['message' => '成绩尚未评定，暂不能申诉'], 422);
        }

        // 校验题目属于该试卷
        if ($request->question_id) {
            $belongs = $record->examPaper->questions()->where('questions.id', $request->question_id)->exists();
            if (!$belongs) {
                return response()->json(['message' => '所申诉的题目不属于本次考试试卷'], 422);
            }
        }

        // 同一题目（或整卷）存在处理中的申诉时，禁止重复提交
        $existsOpenQuery = ScoreAppeal::where('exam_record_id', $record->id)
            ->where('status', '!=', ScoreAppeal::STATUS_COMPLETED);
        if ($request->question_id) {
            $existsOpenQuery->where('question_id', $request->question_id);
        } else {
            $existsOpenQuery->whereNull('question_id');
        }
        if ($existsOpenQuery->exists()) {
            return response()->json(['message' => '该题目已有正在复核中的申诉，请勿重复提交'], 422);
        }

        $appeal = DB::transaction(function () use ($request, $user, $record) {
            $appeal = ScoreAppeal::create([
                'exam_record_id' => $record->id,
                'question_id' => $request->question_id,
                'student_id' => $user->id,
                'appeal_type' => $request->appeal_type,
                'reason' => $request->reason,
                'status' => ScoreAppeal::STATUS_PENDING,
                'original_score' => $record->score,
            ]);

            if ($request->hasFile('evidences')) {
                foreach ($request->file('evidences') as $file) {
                    $ext = $file->getClientOriginalExtension();
                    $path = $file->storeAs(
                        'appeal_evidences/appeal_' . $appeal->id,
                        uniqid() . ($ext ? '.' . $ext : ''),
                        'public'
                    );

                    ScoreAppealEvidence::create([
                        'score_appeal_id' => $appeal->id,
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'uploaded_by' => $user->id,
                    ]);
                }
            }

            return $appeal;
        });

        return response()->json([
            'message' => '申诉已提交，请等待老师复核',
            'appeal' => $this->formatAppeal($appeal->load(['question', 'evidences', 'reviews.reviewer'])),
        ], 201);
    }

    /**
     * 学生查看自己的申诉列表（含复核轨迹）
     */
    public function myIndex(Request $request)
    {
        $appeals = ScoreAppeal::with([
                'question:id,title,type',
                'examRecord:id,exam_paper_id,score,status',
                'examRecord.examPaper:id,title',
                'evidences:id,score_appeal_id,original_name,size,mime_type,created_at',
                'reviews.reviewer:id,username,real_name,role',
            ])
            ->where('student_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'appeals' => $this->formatPaginated($appeals),
        ] + $this->appealsPagination($appeals));
    }

    /**
     * 申诉详情（学生本人 / 教师 / 教务可看，完整处理轨迹）
     */
    public function show(Request $request, ScoreAppeal $appeal)
    {
        $user = $request->user();

        if ($user->isStudent() && $appeal->student_id !== $user->id) {
            return response()->json(['message' => '无权查看此申诉'], 403);
        }

        $appeal->load([
            'student:id,username,real_name,role',
            'question:id,title,type,answer,analysis',
            'examRecord.examPaper:id,title,total_score',
            'examRecord.answers',
            'evidences.uploader:id,username,real_name',
            'reviews.reviewer:id,username,real_name,role',
            'closer:id,username,real_name,role',
        ]);

        return response()->json([
            'appeal' => $this->formatAppeal($appeal, true),
        ]);
    }

    /* ===================== 教师 / 教务端 ===================== */

    /**
     * 复核列表
     * - 老师：待复核 + 自己转过的 + 所有已结案（可查轨迹）；教务（admin）可见全部
     */
    public function reviewIndex(Request $request)
    {
        $user = $request->user();

        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $query = ScoreAppeal::with([
            'student:id,username,real_name',
            'question:id,title,type',
            'examRecord:id,exam_paper_id,score,user_id',
            'examRecord.examPaper:id,title',
            'reviews.reviewer:id,username,real_name,role',
            'evidences:id,score_appeal_id,original_name',
        ]);

        // 老师看不到其他老师尚未处理的待复核？默认所有老师都可处理待复核单
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('appeal_type')) {
            $query->where('appeal_type', $request->input('appeal_type'));
        }
        if ($request->filled('keyword')) {
            $kw = '%' . $request->input('keyword') . '%';
            $query->whereHas('student', function ($q) use ($kw) {
                $q->where('username', 'like', $kw)->orWhere('real_name', 'like', $kw);
            });
        }

        // 教师默认只看待复核与转教务的工单；教务可看全部
        if ($user->isTeacher() && !$user->isAdmin() && !$request->filled('status')) {
            $query->whereIn('status', [ScoreAppeal::STATUS_PENDING, ScoreAppeal::STATUS_TO_ACADEMIC]);
        }

        $appeals = $query->orderByRaw("FIELD(status, 'pending', 'to_academic', 'completed')")
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        $counts = [
            'pending' => ScoreAppeal::where('status', ScoreAppeal::STATUS_PENDING)->count(),
            'to_academic' => ScoreAppeal::where('status', ScoreAppeal::STATUS_TO_ACADEMIC)->count(),
            'completed' => ScoreAppeal::where('status', ScoreAppeal::STATUS_COMPLETED)->count(),
        ];

        return response()->json([
            'appeals' => $this->formatPaginated($appeals),
            'counts' => $counts,
        ] + $this->appealsPagination($appeals));
    }

    /**
     * 老师/教务复核：维持、加分、减分、转教务
     */
    public function review(Request $request, ScoreAppeal $appeal)
    {
        $user = $request->user();

        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        if ($appeal->isClosed()) {
            return response()->json(['message' => '该申诉已复核完成，无法再次处理'], 422);
        }

        // 转教务的工单只有教务（admin）可以继续处理
        if ($appeal->status === ScoreAppeal::STATUS_TO_ACADEMIC && !$user->isAdmin()) {
            return response()->json(['message' => '该申诉已转教务处理，教师无权操作'], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:' . implode(',', array_keys(ScoreAppealReview::ACTIONS)),
            'comment' => 'required|string|min:1|max:1000',
            'score_adjustment' => 'required_if:action,add,deduct|nullable|numeric',
        ], [
            'action.required' => '请选择复核动作',
            'comment.required' => '请填写复核意见，处理意见将留档',
            'score_adjustment.required_if' => '请填写分值调整',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $action = $request->input('action');
        $adjustment = $request->input('score_adjustment') !== null
            ? (float) $request->input('score_adjustment')
            : null;

        if ($action === ScoreAppealReview::ACTION_ADD && ($adjustment === null || $adjustment <= 0)) {
            return response()->json([
                'errors' => ['score_adjustment' => ['加分时调整分值必须为正数']],
            ], 422);
        }
        if ($action === ScoreAppealReview::ACTION_DEDUCT && ($adjustment === null || $adjustment >= 0)) {
            return response()->json([
                'errors' => ['score_adjustment' => ['减分时调整分值必须为负数']],
            ], 422);
        }

        // 转教务只能由教师发起，教务为最终复核环节
        if ($action === ScoreAppealReview::ACTION_TRANSFER && $user->isAdmin()) {
            return response()->json([
                'errors' => ['action' => ['教务为最终复核环节，不能继续转办']],
            ], 422);
        }

        $record = $appeal->examRecord()->with('examPaper')->first();
        $paper = $record->examPaper;

        $scoreAfter = null;

        if ($action === ScoreAppealReview::ACTION_TRANSFER) {
            $appeal->update([
                'status' => ScoreAppeal::STATUS_TO_ACADEMIC,
                'final_result' => ScoreAppeal::RESULT_TRANSFER,
            ]);
        } else {
            // 终局处理：维持 / 加分 / 减分 => 复核完成
            $newTotal = (float) $record->score;
            $effectiveDelta = 0;

            if (in_array($action, [ScoreAppealReview::ACTION_ADD, ScoreAppealReview::ACTION_DEDUCT], true)) {
                if ($appeal->question_id) {
                    // 针对单题：调整该题答案得分，再按全部答案重算总分
                    $answer = $record->answers()->where('question_id', $appeal->question_id)->first();
                    if (!$answer) {
                        return response()->json(['message' => '未找到该题的作答记录，无法调整分值'], 422);
                    }

                    $paperQuestion = DB::table('exam_paper_questions')
                        ->where('exam_paper_id', $paper->id)
                        ->where('question_id', $appeal->question_id)
                        ->first();
                    $maxScore = $paperQuestion ? (float) $paperQuestion->score : null;

                    $oldAnswerScore = (float) $answer->score;
                    $newAnswerScore = $oldAnswerScore + $adjustment;
                    if ($newAnswerScore < 0) {
                        $newAnswerScore = 0;
                    }
                    if ($maxScore !== null && $newAnswerScore > $maxScore) {
                        $newAnswerScore = $maxScore;
                    }
                    $answer->update(['score' => $newAnswerScore]);

                    // 按全部作答题目重新计算总分（直接查库，避免内存模型脏数据）
                    $newTotal = (float) $record->answers()->sum('score');
                    $effectiveDelta = $newTotal - (float) $record->score;
                } else {
                    // 整卷申诉：直接调整总分
                    $newTotal = (float) $record->score + $adjustment;
                    if ($newTotal < 0) {
                        $newTotal = 0;
                    }
                    if ($newTotal > (float) $paper->total_score) {
                        $newTotal = (float) $paper->total_score;
                    }
                    $effectiveDelta = $newTotal - (float) $record->score;
                }

                $record->update(['score' => $newTotal]);
            }

            $resultMap = [
                ScoreAppealReview::ACTION_MAINTAIN => ScoreAppeal::RESULT_MAINTAIN,
                ScoreAppealReview::ACTION_ADD => ScoreAppeal::RESULT_ADD,
                ScoreAppealReview::ACTION_DEDUCT => ScoreAppeal::RESULT_DEDUCT,
            ];

            $appeal->update([
                'status' => ScoreAppeal::STATUS_COMPLETED,
                // 覆盖转教务阶段的临时结论，最终以维持/加分/减分为准（成绩单统一显示复核完成）
                'final_result' => $resultMap[$action],
                'final_score' => $newTotal,
                'score_adjustment' => $action === ScoreAppealReview::ACTION_MAINTAIN
                    ? 0
                    : $effectiveDelta,
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);

            $scoreAfter = $newTotal;
        }

        // 每个处理人的动作与意见都留档，形成完整复核轨迹
        ScoreAppealReview::create([
            'score_appeal_id' => $appeal->id,
            'reviewer_id' => $user->id,
            'reviewer_role' => $user->role,
            'action' => $action,
            'comment' => $request->input('comment'),
            'score_adjustment' => in_array($action, [ScoreAppealReview::ACTION_ADD, ScoreAppealReview::ACTION_DEDUCT], true)
                ? $appeal->score_adjustment
                : 0,
            'score_after' => $scoreAfter,
        ]);

        $appeal->load(['reviews.reviewer', 'evidences', 'question']);

        return response()->json([
            'message' => $action === ScoreAppealReview::ACTION_TRANSFER
                ? '已转教务处理'
                : '复核完成，结果已记录到成绩单',
            'appeal' => $this->formatAppeal($appeal),
        ]);
    }

    /**
     * 下载证据附件（鉴权：学生本人或教师/教务）
     */
    public function downloadEvidence(Request $request, ScoreAppealEvidence $evidence)
    {
        $user = $request->user();
        $evidence->load('appeal');

        if (!$evidence->appeal) {
            return response()->json(['message' => '附件不存在'], 404);
        }

        if ($user->isStudent() && $evidence->appeal->student_id !== $user->id) {
            return response()->json(['message' => '无权下载该附件'], 403);
        }

        if (!Storage::disk('public')->exists($evidence->path)) {
            return response()->json(['message' => '附件文件已丢失'], 404);
        }

        return Storage::disk('public')->download($evidence->path, $evidence->original_name);
    }

    /* ===================== 数据格式化 ===================== */

    private function formatAppeal(ScoreAppeal $appeal, bool $withDetail = false): array
    {
        $data = [
            'id' => $appeal->id,
            'exam_record_id' => $appeal->exam_record_id,
            'question_id' => $appeal->question_id,
            'student_id' => $appeal->student_id,
            'appeal_type' => $appeal->appeal_type,
            'appeal_type_text' => ScoreAppeal::TYPES[$appeal->appeal_type] ?? $appeal->appeal_type,
            'reason' => $appeal->reason,
            'status' => $appeal->status,
            'status_text' => ScoreAppeal::STATUSES[$appeal->status] ?? $appeal->status,
            'original_score' => $appeal->original_score,
            'final_score' => $appeal->final_score,
            'score_adjustment' => $appeal->score_adjustment,
            'final_result' => $appeal->final_result,
            'final_result_text' => $appeal->final_result
                ? (ScoreAppeal::RESULTS[$appeal->final_result] ?? $appeal->final_result)
                : null,
            'closed_at' => $appeal->closed_at,
            'created_at' => $appeal->created_at,
            'student' => $appeal->relationLoaded('student') && $appeal->student ? [
                'id' => $appeal->student->id,
                'username' => $appeal->student->username,
                'real_name' => $appeal->student->real_name,
            ] : null,
            'question' => $appeal->relationLoaded('question') && $appeal->question ? [
                'id' => $appeal->question->id,
                'title' => $appeal->question->title,
                'type' => $appeal->question->type,
            ] : null,
            'exam_paper' => $appeal->relationLoaded('examRecord') && $appeal->examRecord
                ? ($appeal->examRecord->relationLoaded('examPaper') && $appeal->examRecord->examPaper ? [
                    'id' => $appeal->examRecord->examPaper->id,
                    'title' => $appeal->examRecord->examPaper->title,
                    'total_score' => $appeal->examRecord->examPaper->total_score,
                ] : null)
                : null,
            'record_score' => $appeal->relationLoaded('examRecord') && $appeal->examRecord
                ? $appeal->examRecord->score
                : null,
            'evidences' => $appeal->relationLoaded('evidences')
                ? $appeal->evidences->map(fn ($e) => [
                    'id' => $e->id,
                    'original_name' => $e->original_name,
                    'mime_type' => $e->mime_type,
                    'size' => $e->size,
                    'created_at' => $e->created_at,
                    'uploader' => $e->relationLoaded('uploader') && $e->uploader ? [
                        'id' => $e->uploader->id,
                        'username' => $e->uploader->username,
                        'real_name' => $e->uploader->real_name,
                    ] : null,
                ])->values()
                : [],
            // 复核轨迹：按时间顺序保留每个处理人的意见
            'reviews' => $appeal->relationLoaded('reviews')
                ? $appeal->reviews->map(fn ($r) => [
                    'id' => $r->id,
                    'action' => $r->action,
                    'action_text' => ScoreAppealReview::ACTIONS[$r->action] ?? $r->action,
                    'comment' => $r->comment,
                    'score_adjustment' => $r->score_adjustment,
                    'score_after' => $r->score_after,
                    'created_at' => $r->created_at,
                    'reviewer' => $r->relationLoaded('reviewer') && $r->reviewer ? [
                        'id' => $r->reviewer->id,
                        'username' => $r->reviewer->username,
                        'real_name' => $r->reviewer->real_name,
                        'role' => $r->reviewer->role,
                        'role_text' => $r->reviewer->role === 'admin' ? '教务' : '教师',
                    ] : [
                        'id' => $r->reviewer_id,
                        'role_text' => $r->reviewer_role === 'admin' ? '教务' : '教师',
                    ],
                ])->values()
                : [],
        ];

        if ($withDetail) {
            $record = $appeal->examRecord;
            $data['appealed_answer'] = null;
            if ($record && $appeal->question_id && $record->relationLoaded('answers')) {
                $answer = $record->answers->firstWhere('question_id', $appeal->question_id);
                if ($answer) {
                    $data['appealed_answer'] = [
                        'answer' => $answer->answer,
                        'is_correct' => $answer->is_correct,
                        'score' => $answer->score,
                        'correct_answer' => $appeal->question?->answer,
                        'analysis' => $appeal->question?->analysis,
                    ];
                }
            }
        }

        return $data;
    }

    private function formatPaginated($paginator): array
    {
        return $paginator->map(fn ($a) => $this->formatAppeal($a))->all();
    }

    private function appealsPagination($paginator): array
    {
        return [
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
