<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_appeal_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('score_appeal_id')->comment('申诉ID');
            $table->unsignedBigInteger('handler_id')->comment('处理人ID');
            $table->string('handler_role', 20)->comment('处理时角色');
            $table->string('action', 30)->comment('处理动作: submit/transfer/upheld/add_score/deduct_score');
            $table->string('result', 30)->nullable()->comment('处理后状态: pending/transferred/closed');
            $table->decimal('score_adjustment', 6, 2)->default(0)->comment('分值调整(正加负减)');
            $table->decimal('score_before', 6, 2)->nullable()->comment('处理前成绩');
            $table->decimal('score_after', 6, 2)->nullable()->comment('处理后成绩');
            $table->text('comment')->nullable()->comment('处理意见');
            $table->unsignedBigInteger('assigned_from')->nullable()->comment('转出人ID');
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('接收人ID');
            $table->timestamps();

            $table->index('score_appeal_id');
            $table->index('handler_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_appeal_reviews');
    }
};
