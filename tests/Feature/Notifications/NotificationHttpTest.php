<?php

use App\Domains\Notifications\Notifications\BaseNotification;
use App\Models\User;

class NotifikasiHttpUji extends BaseNotification
{
    public function title(): string
    {
        return 'Judul';
    }

    public function body(): string
    {
        return 'Isi';
    }

    public function url(): string
    {
        return '/dashboard';
    }
}

test('tamu diarahkan ke login', function () {
    $this->get('/notifications')->assertRedirect('/login');
    $this->post('/notifications/mark-all-read')->assertRedirect('/login');
});

test('daftar menampilkan notifikasi sendiri dan filter belum dibaca', function () {
    $user = User::factory()->create();
    $user->notify(new NotifikasiHttpUji);
    $user->notify(new NotifikasiHttpUji);
    $user->notifications()->first()->markAsRead();

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn ($p) => $p->component('Notifikasi/Index')->has('items.data', 2));

    $this->actingAs($user)->get('/notifications?filter[status]=unread')
        ->assertInertia(fn ($p) => $p->has('items.data', 1));
});

test('tandai satu dan semua dibaca', function () {
    $user = User::factory()->create();
    $user->notify(new NotifikasiHttpUji);
    $user->notify(new NotifikasiHttpUji);
    $id = $user->notifications()->first()->id;

    $this->actingAs($user)->post("/notifications/{$id}/mark-read")->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)->post('/notifications/mark-all-read')->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
});

test('notifikasi milik orang lain tidak bisa ditandai (404)', function () {
    $pemilik = User::factory()->create();
    $lain = User::factory()->create();
    $pemilik->notify(new NotifikasiHttpUji);

    $this->actingAs($lain)->post('/notifications/'.$pemilik->notifications()->first()->id.'/mark-read')->assertNotFound();
});

test('prop halaman tidak menimpa shared prop notifications di halaman notifikasi', function () {
    $user = User::factory()->create();
    $user->notify(new NotifikasiHttpUji);

    $this->actingAs($user)->get('/notifications')
        ->assertInertia(fn ($p) => $p->where('notifications.unread_count', 1)->has('notifications.latest', 1)->has('items.data', 1));
});

test('shared prop notifications berisi ringkasan untuk user dan null untuk tamu', function () {
    $user = User::factory()->create();
    $user->notify(new NotifikasiHttpUji);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($p) => $p->where('notifications.unread_count', 1)->has('notifications.latest', 1));

    auth()->logout();
    $this->get('/login')->assertInertia(fn ($p) => $p->where('notifications', null));
});
