<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly array $notification)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('dashboard.notifications')];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.notification.created';
    }

    public function broadcastWith(): array
    {
        return ['notification' => $this->notification];
    }
}