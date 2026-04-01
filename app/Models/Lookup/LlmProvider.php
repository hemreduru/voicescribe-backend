<?php

namespace App\Models\Lookup;

use App\Models\Summary;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LlmProvider extends BaseLookup
{
    protected $table = 'llm_providers';

    public const KEY_LOCAL = 'local';
    public const KEY_OPENAI = 'openai';
    public const KEY_CLAUDE = 'claude';
    public const KEY_GEMINI = 'gemini';

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class, 'provider_id');
    }
}
