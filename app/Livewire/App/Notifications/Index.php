<?php

namespace App\Livewire\App\Notifications;

use App\Models\DatabaseNotification;
use App\Models\NotificationLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Notifications - DocPilot')]
class Index extends Component
{
    public string $filter = 'all'; // all, unread, emails

    public function mount(): void
    {
        $this->authorize('view', auth()->user()->company);
    }

    public function markAsRead(string $id): void
    {
        $this->authorize('interact', auth()->user()->company);

        DatabaseNotification::where('id', $id)
            ->where('notifiable_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        $this->authorize('interact', auth()->user()->company);

        DatabaseNotification::where('notifiable_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function render()
    {
        $query = DatabaseNotification::where('notifiable_id', auth()->id())
            ->orderByDesc('created_at');

        $unreadCount = (clone $query)->whereNull('read_at')->count();

        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        }

        $items = $query->limit(100)->get()->map(function (DatabaseNotification $n): array {
            $data = is_array($n->data) ? $n->data : [];
            $createdAt = $n->created_at ?? now();

            $group = match (true) {
                $createdAt->isToday() => 'Aujourd\'hui',
                $createdAt->isYesterday() => 'Hier',
                default => $createdAt->format('d/m/Y'),
            };

            [$icon, $color] = match (true) {
                str_contains($n->type, 'uploaded') => ['document', 'purple'],
                str_contains($n->type, 'expir') => ['exclamation-triangle', 'amber'],
                str_contains($n->type, 'rejected') => ['x-circle', 'red'],
                str_contains($n->type, 'validated') => ['check-circle', 'green'],
                default => ['bell', 'blue'],
            };

            return [
                'id' => (string) $n->id,
                'group' => $group,
                'icon' => $icon,
                'color' => $color,
                'title' => $data['title'] ?? 'Notification',
                'text' => $data['body'] ?? ($data['message'] ?? ''),
                'time' => $createdAt->isToday() ? $createdAt->format('H:i') : $createdAt->diffForHumans(),
                'read' => $n->read_at !== null,
            ];
        });

        $groupedNotifications = $items->groupBy('group');

        $emailLogs = NotificationLog::orderByDesc('sent_at')
            ->limit(50)
            ->get()
            ->map(fn (NotificationLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'channel' => $log->channel,
                'recipient' => $log->recipient,
                'dedup_key' => $log->dedup_key,
                'sent_at' => $log->sent_at?->format('d/m/Y H:i:s') ?? '-',
            ]);

        return view('livewire.app.notifications.index', [
            'groupedNotifications' => $groupedNotifications,
            'unreadCount' => $unreadCount,
            'emailLogs' => $emailLogs,
        ]);
    }
}
