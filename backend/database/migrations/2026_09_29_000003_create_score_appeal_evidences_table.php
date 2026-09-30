<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_appeal_evidences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('score_appeal_id')->comment('申诉ID');
            $table->string('original_name')->comment('原始文件名');
            $table->string('path')->comment('存储路径');
            $table->string('mime_type')->nullable()->comment('文件类型');
            $table->unsignedBigInteger('size')->default(0)->comment('文件大小(字节)');
            $table->unsignedBigInteger('uploaded_by')->comment('上传人ID');
            $table->timestamps();

            $table->index('score_appeal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_appeal_evidences');
    }
};
