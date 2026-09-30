<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_record_id')->comment('考试记录ID');
            $table->unsignedBigInteger('student_id')->comment('申诉学生ID');
            $table->unsignedBigInteger('question_id')->nullable()->comment('申诉题目ID，整体申诉时为空');
            $table->string('type', 30)->default('score')->comment('申诉类型: score-分数 judge-判题 abnormal-异常标记');
            $table->text('reason')->comment('申诉原因');
            $table->string('status', 30)->default('pending')->comment('状态');
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('当前处理人ID');
            $table->timestamp('submitted_at')->nullable()->comment('申诉提交时间');
            $table->timestamp('closed_at')->nullable()->comment('复核完成时间');
            $table->timestamps();

            $table->index('exam_record_id');
            $table->index('student_id');
            $table->index('question_id');
            $table->index('status');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_appeals');
    }
};
