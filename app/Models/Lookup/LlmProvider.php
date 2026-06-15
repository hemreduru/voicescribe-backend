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

    /**
     * Resolve a Summary.provider_id from a client provider key. The client sends
     * 'local' (on-device) or 'cloud'; 'cloud' maps to the configured default
     * remote provider so swapping the remote LLM stays a config change (see
     * config/llm.php). Unknown keys fall back to 'local' — never to an arbitrary
     * provider — so an on-device summary is never mislabeled as a paid one.
     */
    public static function resolveId(string $providerKey): int
    {
        $key = $providerKey === 'cloud'
            ? (string) config('llm.default_provider')
            : $providerKey;

        return static::getIdByKey($key)
            ?? static::getIdByKey(self::KEY_LOCAL)
            ?? (int) static::query()->value('id');
    }
}
