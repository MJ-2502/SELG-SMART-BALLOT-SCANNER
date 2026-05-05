<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ElectionCompleted implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public int $electionId;
    public ?int $reportId;

    /**
     * Create a new event instance.
     */
    public function __construct(int $electionId, ?int $reportId = null)
    {
        $this->electionId = $electionId;
        $this->reportId = $reportId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new Channel('election.' . $this->electionId);
    }

    /**
     * Data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        return [
            'election_id' => $this->electionId,
            'report_id' => $this->reportId,
            'status' => 'completed',
        ];
    }

    public function broadcastAs(): string
    {
        return 'ElectionCompleted';
    }
}
