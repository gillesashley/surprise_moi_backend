<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }
}
