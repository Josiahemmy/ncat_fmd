<?php

namespace App\Services\Demo;

use App\Models\DemoState;
use Illuminate\Support\Facades\Cache;

/**
 * The demo-mode flag (Builder Prompt #7). Backed by the demo_states table and a
 * short cache so the per-request "is a demo running?" check (shared to Inertia
 * + read by the PDF watermark) costs nothing. Activated by demo:seed, cleared
 * by demo:purge.
 */
class DemoMode
{
    protected const CACHE_KEY = 'demo_mode.active';

    public function isActive(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY, now()->addSeconds(30), fn () => DemoState::query()->where('active', true)->exists());
    }

    /**
     * Turn demo mode on and snapshot the current document-counter values so
     * purge can restore them.
     *
     * @param  array<string, int>  $counterSnapshot  series => next_number
     */
    public function activate(array $counterSnapshot): DemoState
    {
        $state = DemoState::query()->firstOrNew([]);
        $state->fill([
            'active' => true,
            'counters_snapshot' => $counterSnapshot,
            'seeded_at' => now(),
        ])->save();

        $this->bust();

        return $state;
    }

    public function deactivate(): void
    {
        DemoState::query()->update(['active' => false]);
        $this->bust();
    }

    /** The counter snapshot captured at seed time, or null. */
    public function counterSnapshot(): ?array
    {
        return DemoState::query()->latest('id')->value('counters_snapshot');
    }

    public function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
