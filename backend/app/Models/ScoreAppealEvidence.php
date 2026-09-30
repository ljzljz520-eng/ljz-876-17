<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreAppealEvidence extends Model
{
    protected $fillable = [
        'score_appeal_id',
        'original_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'score_appeal_id' => 'integer',
        'size' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function appeal()
    {
        return $this->belongsTo(ScoreAppeal::class, 'score_appeal_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
