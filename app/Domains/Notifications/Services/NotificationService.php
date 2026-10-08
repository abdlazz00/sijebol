<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Notifications\BaseNotification;
use App\Domains\Notifications\Repositories\Contracts\NotificationRepositoryInterface;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications) {}

    /** Mengirim ke semua pengguna aktif pemilik permission; mengembalikan jumlah penerima. */
    public function toPermission(string $permission, BaseNotification $notification, ?int $schoolId = null): int
    {
        $recipients = $this->notifications->recipientsWithPermission($permission, $schoolId);
        Notification::send($recipients, $notification);

        return $recipients->count();
    }

    /** @return LengthAwarePaginator<int, array{id: string, title: string, body: string, url: string, read_at: ?string, created_at: string}> */
    public function list(User $user, bool $unreadOnly): LengthAwarePaginator
    {
        return $this->notifications->paginateFor($user, $unreadOnly)
            ->through(fn (DatabaseNotification $n) => $this->row($n));
    }

    public function markRead(User $user, string $id): void
    {
        $this->notifications->markRead($this->notifications->findFor($user, $id));
    }

    public function markAllRead(User $user): void
    {
        $this->notifications->markAllRead($user);
    }

    /** @return array{unread_count: int, latest: list<array<string, mixed>>} */
    public function summary(User $user): array
    {
        return [
            'unread_count' => $this->notifications->unreadCount($user),
            'latest' => $this->notifications->latestFor($user)
                ->map(fn (DatabaseNotification $n) => $this->row($n))->values()->all(),
        ];
    }

    /** @return array{id: string, title: string, body: string, url: string, read_at: ?string, created_at: string} */
    private function row(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'title' => (string) ($n->data['title'] ?? ''),
            'body' => (string) ($n->data['body'] ?? ''),
            'url' => (string) ($n->data['url'] ?? '/dashboard'),
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
