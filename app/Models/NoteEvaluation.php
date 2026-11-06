<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteEvaluation extends Model
{
    protected $table = 'notes_evaluations';

    protected $fillable = ['evaluation_id', 'eleve_id', 'note'];

    protected $casts = ['note' => 'float'];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}
