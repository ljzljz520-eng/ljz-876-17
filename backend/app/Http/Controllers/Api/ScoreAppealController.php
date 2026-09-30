<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamRecord;
use App\Models\ExamRecordAnswer;
use App\Models\ScoreAppeal;
use App\Models\ScoreAppealEvidence;
use App\Models\ScoreAppealReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ScoreAppealController extends Controller
{
    /**
     * 学生：我的申诉列表
     */
    public function myIndex(Request $request)
    {
        $appeals = ScoreAppeal::with(['examRecord.examPaper', 'question', 'assignee'])
            ->where('student_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json(['appeals' => $appeals]);
    }

    /**
     * 教师/教务：复核队列
     */
    public function reviewIndex(Request $request)
    {
        $user = $request->user();

        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $baseQuery = ScoreAppeal::query();

        // 教师只看待复核与自己处理过的；教务看全部（含已转教务）
        if ($user->isTeacher() && !$user->isAdmin()) {
            $baseQuery->where(function ($q) use ($user) {
                $q->where('status', ScoreAppeal::STATUS_PENDING)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereExists(function ($sub) use ($user) {
                        $sub->select(DB::raw(1))
                            ->from('score_appeal_reviews')
                            ->whereColumn('score_appeal_reviews.score_appeal_id', 'score_appeals.id')
                            ->where('score_appeal_reviews.handler_id', $user->id);
                    });
            });
        }

        if ($keyword = $request->input('keyword')) {
            $baseQuery->whereHas('student', function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('real_name', 'like', "%{$keyword}%");
            });
        }

        $counts = [
            'pending' => (clone $baseQuery)->where('status', ScoreAppeal::STATUS_PENDING)->count(),
            'transferred' => (clone $baseQuery)->where('status', ScoreAppeal::STATUS_TRANSFERRED)->count(),
            'closed' => (clone $baseQuery)->where('status', ScoreAppeal::STATUS_CLOSED)->count(),
        ];

        $query = ScoreAppeal::with(['examRecord.examPaper', 'student', 'question', 'assignee'])
            ->orderByRaw("FIELD(status, 'pending', 'transferred', 'closed')")
            ->orderBy('id', 'desc');

        if ($user->isTeacher() && !$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('status', ScoreAppeal::STATUS_PENDING)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereExists(function ($sub) use ($user) {
                        $sub->select(DB::raw(1))
                            ->from('score_appeal_reviews')
                            ->whereColumn('score_appeal_reviews.score_appeal_id', 'score_appeals.id')
                            ->where('score_appeal_reviews.handler_id', $user->id);
                    });
            });
        }

        if ($keyword = $request->input('keyword')) {
            $query->whereHas('student', function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('real_name', 'like', "%{$keyword}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'appeals' => $query->paginate($request->input('per_page', 15)),
            'counts' => $counts,
        ]);
    }

    /**
     * 学生：成绩详情（申诉时选择题目）
     */
    public function recordQuestions(Request $request, ExamRecord $record)
    {
        $user = $request->user();

        if ($user->isStudent() && $record->user_id !== $user->id) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        if (!$user->isStudent() && !$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        $record->load(['examPaper:id,title,total_score', 'answers.question:id,title,type']);

        $questions = $record->examPaper->questions()->get()->map(function ($q) use ($record) {
            $answer = $record->answers->firstWhere('question_id', $q->id);

            return [
                'id' => $q->id,
                'type' => $q->type,
                'title' => $q->title,
                'paper_score' => $q->pivot->score,
                'answer_score' => $answer?->score,
                'is_correct' => $answer?->is_correct,
                'student_answer' => $answer?->answer,
            ];
        });

        return response()->json([
            'record' => [
                'id' => $record->id,
                'score' => $record->score,
                'status' => $record->status,
                'exam_paper' => $record->examPaper,
            ],
            'questions' => $questions,
        ]);
    }

    /**
     * 学生：发起申诉（含证据上传，multipart/form-data）
     */
    public function store(Request $request)
    {
        if (!$request->user()->isStudent()) {
            return response()->json(['message' => '仅学生可以发起成绩申诉'], 403);
        }

        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'question_id' => 'nullable|exists:questions,id',
            'type' => 'required|in:score,judge,abnormal',
            'reason' => 'required|string|min:5|max:1000',
            'evidences' => 'nullable|array|max:5',
            'evidences.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip',
        ], [
            'reason.required' => '请填写申诉原因',
            'reason.min' => '申诉原因至少 5 个字符',
            'evidences.max' => '最多上传 5 个证据文件',
            'evidences.*.max' => '单个证据文件不能超过 10MB',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::with('examPaper')->findOrFail($request->exam_record_id);

        if ($record->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权对该成绩发起申诉'], 403);
        }

        if ($record->status !== ExamRecord::STATUS_GRADED) {
            return response()->json(['message' => '成绩尚未评定，暂不能申诉'], 422);
        }

        if ($request->question_id) {
            $belongsToPaper = DB::table('exam_paper_questions')
                ->where('exam_paper_id', $record->exam_paper_id)
                ->where('question_id', $request->question_id)
                ->exists();

            if (!$belongsToPaper) {
                return response()->json(['message' => '申诉题目不属于该试卷'], 422);
            }
        }

        $openAppeal = ScoreAppeal::where('exam_record_id', $record->id)
            ->where('student_id', $request->user()->id)
            ->whereIn('status', [ScoreAppeal::STATUS_PENDING, ScoreAppeal::STATUS_TRANSFERRED])
            ->exists();

        if ($openAppeal) {
            return response()->json(['message' => '该成绩已有正在处理中的申诉，请等待复核完成'], 422);
        }

        $appeal = DB::transaction(function () use ($request, $record) {
            $appeal = ScoreAppeal::create([
                'exam_record_id' => $record->id,
                'student_id' => $request->user()->id,
                'question_id' => $request->question_id ?: null,
                'type' => $request->type,
                'reason' => $request->reason,
                'status' => ScoreAppeal::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            ScoreAppealReview::create([
                'score_appeal_id' => $appeal->id,
                'handler_id' => $request->user()->id,
                'handler_role' => $request->user()->role,
                'action' => ScoreAppealReview::ACTION_SUBMIT,
                'result' => ScoreAppeal::STATUS_PENDING,
                'comment' => $request->reason,
            ]);

            if ($request->hasFile('evidences')) {
                foreach ($request->file('evidences') as $file) {
                    if (!$file->isValid()) {
                        continue;
                    }
                    $path = $file->store("appeal_evidences/{$appeal->id}");
                    ScoreAppealEvidence::create([
                        'score_appeal_id' => $appeal->id,
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ]);
                }
            }

            return $appeal;
        });

        return response()->json([
            'message' => '申诉已提交，等待老师复核',
            'appeal' => $this->loadAppealDetail($appeal->id),
        ], 201);
    }

    /**
     * 申诉详情（含处理轨迹与证据）
     */
    public function show(Request $request, ScoreAppeal $appeal)
    {
        $user = $request->user();

        if ($user->isStudent() && $appeal->student_id !== $user->id) {
            return response()->json(['message' => '无权查看此申诉'], 403);
        }

        if (!$user->isStudent() && !$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权查看此申诉'], 403);
        }

        return response()->json(['appeal' => $this->loadAppealDetail($appeal->id)]);
    }

    /**
     * 教师/教务：提交复核意见（维持 / 加分 / 减分 / 转交教务）
     */
    public function review(Request $request, ScoreAppeal $appeal)
    {
        $user = $request->user();

        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json(['message' => '无权进行复核'], 403);
        }

        if ($appeal->status === ScoreAppeal::STATUS_CLOSED) {
            return response()->json(['message' => '该申诉已复核完成，不能重复处理'], 422);
        }

        // 已转教务的申诉仅教务（admin）可继续处理
        if ($appeal->status === ScoreAppeal::STATUS_TRANSFERRED && !$user->isAdmin()) {
            return response()->json(['message' => '该申诉已转交教务，仅教务可处理'], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:upheld,add_score,deduct_score,transfer',
            'comment' => 'required|string|min:2|max:1000',
            'adjustment' => 'required_if:action,add_score,deduct_score|nullable|numeric|min:0.01|max:999',
            'assignee_id' => 'nullable|exists:users,id',
        ], [
            'comment.required' => '请填写复核意见',
            'comment.min' => '复核意见至少 2 个字符',
            'adjustment.required_if' => '请填写调整分值',
            'adjustment.min' => '调整分值必须大于 0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $action = $request->action;

        if ($action === ScoreAppealReview::ACTION_TRANSFER) {
            if (!$user->isTeacher()) {
                return response()->json(['message' => '仅教师可以将申诉转交教务'], 403);
            }

            $admin = null;
            if ($request->assignee_id) {
                $admin = User::where('id', $request->assignee_id)
                    ->where('role', User::ROLE_ADMIN)
                    ->where('status', 1)
                    ->first();
                if (!$admin) {
                    return response()->json(['message' => '指定的接收人不是教务人员'], 422);
                }
            } else {
                $admin = User::where('role', User::ROLE_ADMIN)->where('status', 1)->orderBy('id')->first();
            }

            if (!$admin) {
                return response()->json(['message' => '系统中暂无教务人员，无法转交'], 422);
            }

            DB::transaction(function () use ($appeal, $user, $admin, $request) {
                $previousAssignee = $appeal->assigned_to;

                $appeal->update([
                    'status' => ScoreAppeal::STATUS_TRANSFERRED,
                    'assigned_to' => $admin->id,
                ]);

                ScoreAppealReview::create([
                    'score_appeal_id' => $appeal->id,
                    'handler_id' => $user->id,
                    'handler_role' => $user->role,
                    'action' => ScoreAppealReview::ACTION_TRANSFER,
                    'result' => ScoreAppeal::STATUS_TRANSFERRED,
                    'comment' => $request->comment,
                    'assigned_from' => $previousAssignee,
                    'assigned_to' => $admin->id,
                ]);
            });

            return response()->json([
                'message' => "已转交教务（{$admin->real_name ?: $admin->username}）",
                'appeal' => $this->loadAppealDetail($appeal->id),
            ]);
        }

        // 维持 / 加分 / 减分 均为终局结论
        $result = DB::transaction(function () use ($appeal, $user, $request, $action) {
            $record = $appeal->examRecord()->lockForUpdate()->first();
            $scoreBefore = (float) $record->score;
            $scoreAfter = $scoreBefore;
            $adjustment = 0.0;
            $previousAssignee = $appeal->assigned_to;

            if (in_array($action, [ScoreAppealReview::ACTION_ADD_SCORE, ScoreAppealReview::ACTION_DEDUCT_SCORE], true)) {
                $delta = (float) $request->adjustment;
                $signedDelta = $action === ScoreAppealReview::ACTION_ADD_SCORE ? $delta : -$delta;
                $maxScore = (float) $record->examPaper->total_score;

                if ($appeal->question_id) {
                    $answer = ExamRecordAnswer::where('exam_record_id', $record->id)
                        ->where('question_id', $appeal->question_id)
                        ->first();

                    if (!$answer) {
                        abort(422, '未找到该题目的作答记录');
                    }

                    $questionScore = (float) DB::table('exam_paper_questions')
                        ->where('exam_paper_id', $record->exam_paper_id)
                        ->where('question_id', $appeal->question_id)
                        ->value('score');

                    $newAnswerScore = max(0, min($questionScore, (float) $answer->score + $signedDelta));
                    $actualDelta = $newAnswerScore - (float) $answer->score;
                    $answer->update(['score' => $newAnswerScore]);

                    $scoreAfter = max(0, min($maxScore, $scoreBefore + $actualDelta));
                    $adjustment = $scoreAfter - $scoreBefore;
                } else {
                    $scoreAfter = max(0, min($maxScore, $scoreBefore + $signedDelta));
                    $adjustment = $scoreAfter - $scoreBefore;
                }

                $record->update(['score' => $scoreAfter]);
            }

            $appeal->update([
                'status' => ScoreAppeal::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            ScoreAppealReview::create([
                'score_appeal_id' => $appeal->id,
                'handler_id' => $user->id,
                'handler_role' => $user->role,
                'action' => $action,
                'result' => ScoreAppeal::STATUS_CLOSED,
                'score_adjustment' => $adjustment,
                'score_before' => $scoreBefore,
                'score_after' => $scoreAfter,
                'comment' => $request->comment,
                'assigned_from' => $previousAssignee,
            ]);

            return compact('scoreBefore', 'scoreAfter', 'adjustment');
        });

        $actionText = ScoreAppealReview::ACTIONS[$action] ?? '复核';

        return response()->json([
            'message' => "复核完成：{$actionText}",
            'appeal' => $this->loadAppealDetail($appeal->id),
            'score_before' => $result['scoreBefore'],
            'score_after' => $result['scoreAfter'],
        ]);
    }

    /**
     * 证据文件下载（申诉学生本人或教师/教务）
     */
    public function downloadEvidence(Request $request, ScoreAppeal $appeal, ScoreAppealEvidence $evidence)
    {
        $user = $request->user();

        if ($evidence->score_appeal_id !== $appeal->id) {
            return response()->json(['message' => '证据不存在'], 404);
        }

        $isParticipant = $user->isTeacher() || $user->isAdmin()
            || ($user->isStudent() && $appeal->student_id === $user->id);

        if (!$isParticipant) {
            return response()->json(['message' => '无权下载该证据'], 403);
        }

        if (!Storage::exists($evidence->path)) {
            return response()->json(['message' => '证据文件已丢失'], 404);
        }

        return Storage::download($evidence->path, $evidence->original_name);
    }

    protected function loadAppealDetail(int $appealId): ScoreAppeal
    {
        return ScoreAppeal::with([
            'examRecord.examPaper',
            'student:id,username,real_name,role',
            'question:id,title,type',
            'assignee:id,username,real_name,role',
            'evidences',
            'reviews.handler:id,username,real_name,role',
            'reviews.fromUser:id,username,real_name,role',
            'reviews.toUser:id,username,real_name,role',
        ])->findOrFail($appealId);
    }
}
