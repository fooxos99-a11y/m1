<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardActivityLogged implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly array $activityLog)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('dashboard.activity')];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.activity.logged';
    }

    public function broadcastWith(): array
    {
        return ['activityLog' => $this->activityLog];
    }
}