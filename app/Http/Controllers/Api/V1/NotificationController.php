<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\VoteSysNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('notifications.view');

        $notifications = VoteSysNotification::query()
            ->where('recipient_external_id', $access->principal->id)
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return $this->json([
            'data' => $notifications->items(),
            'pagination' => [
                'total'        => $notifications->total(),
                'per_page'     => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'unread'       => VoteSysNotification::query()
                    ->where('recipient_external_id', $access->principal->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function markRead(Request $request, VoteSysNotification $notification): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('notifications.view');

        abort_if($notification->recipient_external_id !== $access->principal->id, 403);

        $notification->update(['read_at' => now()]);

        return $this->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $access->authorize('notifications.view');

        VoteSysNotification::query()
            ->where('recipient_external_id', $access->principal->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->json(['message' => 'All notifications marked as read.']);
    }
}
