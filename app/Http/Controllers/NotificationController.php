<?php

namespace App\Http\Controllers;

use App\Support\AdminNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The admin bell (App\Support\AdminNotifications): "View all" page and "mark as read". */
class NotificationController extends Controller
{
    public function index(Request $request){
        [$items] = AdminNotifications::forUser($request->user());
        $messages = $request->user()->can('website.pages')
            ? DB::table('contact_messages')->orderByDesc('id')->limit(50)->get() : collect();
        AdminNotifications::markSeen($request->user()->id);
        return view('backend.pages.notifications', ['items' => $items, 'messages' => $messages]);
    }

    public function read(Request $request){
        AdminNotifications::markSeen($request->user()->id);
        return response()->json(['success' => true]);
    }
}
