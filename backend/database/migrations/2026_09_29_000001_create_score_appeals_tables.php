<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('score_appeals')) {
            Schema::create('score_appeals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('exam_record_id')->comment('考试记录ID');
                $table->unsignedBigInteger('question_id')->nullable()->comment('申诉题目ID(NULL=整卷申诉)');
                $table->unsignedBigInteger('student_id')->comment('申诉学生ID');
                $table->string('appeal_type', 20)->default('score')->comment('申诉类型: score-分数 scoring-判题 abnormal-异常标记');
                $table->text('reason')->comment('申诉原因');
                $table->string('status', 20)->default('pending')->comment('状态: pending-待复核 to_academic-已转教务 completed-已完成');
                $table->decimal('original_score', 6, 2)->nullable()->comment('申诉前总分');
                $table->decimal('final_score', 6, 2)->nullable()->comment('复核后总分');
                $table->decimal('score_adjustment', 6, 2)->nullable()->comment('分数调整值(正为加分,负为减分)');
                $table->string('final_result', 20)->nullable()->comment('最终结论: maintain-维持 add-加分 deduct-减分 transfer-转教务');
                $table->unsignedBigInteger('closed_by')->nullable()->comment('复核结案人ID');
                $table->timestamp('closed_at')->nullable()->comment('结案时间');
                $table->timestamps();

                $table->index('exam_record_id');
                $table->index('question_id');
                $table->index('student_id');
                $table->index('status');
                $table->index('appeal_type');
            });
        }

        if (!Schema::hasTable('score_appeal_evidences')) {
            Schema::create('score_appeal_evidences', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('score_appeal_id')->comment('申诉ID');
                $table->string('original_name')->comment('原始文件名');
                $table->string('path')->comment('存储路径');
                $table->string('mime_type')->nullable()->comment('文件MIME类型');
                $table->unsignedBigInteger('size')->default(0)->comment('文件大小(字节)');
                $table->unsignedBigInteger('uploaded_by')->comment('上传人ID');
                $table->timestamps();

                $table->index('score_appeal_id');
            });
        }

        if (!Schema::hasTable('score_appeal_reviews')) {
            Schema::create('score_appeal_reviews', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('score_appeal_id')->comment('申诉ID');
                $table->unsignedBigInteger('reviewer_id')->comment('处理人ID');
                $table->string('reviewer_role', 20)->comment('处理人角色: teacher/admin');
                $table->string('action', 20)->comment('处理动作: maintain-维持 add-加分 deduct-减分 transfer-转教务');
                $table->text('comment')->nullable()->comment('处理意见');
                $table->decimal('score_adjustment', 6, 2)->nullable()->comment('本次调整分值');
                $table->decimal('score_after', 6, 2)->nullable()->comment('处理后总分');
                $table->timestamps();

                $table->index('score_appeal_id');
                $table->index('reviewer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('score_appeal_reviews');
        Schema::dropIfExists('score_appeal_evidences');
        Schema::dropIfExists('score_appeals');
    }
};
