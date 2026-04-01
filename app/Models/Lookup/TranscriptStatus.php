<?php

namespace App\Models\Lookup;

use App\Models\Transcript;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranscriptStatus extends BaseLookup
{
    protected $table = 'transcript_statuses';

    public const KEY_RECORDING = 'recording';
    public const KEY_PROCESSING = 'processing';
    public const KEY_COMPLETED = 'completed';
    public const KEY_FAILED = 'failed';

    public function transcripts(): HasMany
    {
        return $this->hasMany(Transcript::class, 'status_id');
    }
}
