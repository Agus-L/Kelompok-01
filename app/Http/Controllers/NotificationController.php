<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request, string $id)
    {
        // $notification = \Illuminate\Notifications\DatabaseNotification::findOrFail($id);, Method markAsRead pada NotificationController.php mencari notifikasi langsung via model DatabaseNotification::findOrFail($id) tanpa membatasi kepemilikan, sehingga pengguna mana pun dapat memanipulasi dan menandai notifikasi milik pengguna lain sebagai dibaca cukup dengan memasukkan ID notifikasi orang tersebut (celah IDOR), yang seharusnya dibatasi hanya pada notifikasi milik pengguna yang sedang terotentikasi melalui $request->user()->notifications()->where('id', $id)->firstOrFail().
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return redirect()->back()->with('success', 'Notifikasi ditandai telah dibaca.');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back()->with('success', 'Semua notifikasi ditandai telah dibaca.');
    }
}
