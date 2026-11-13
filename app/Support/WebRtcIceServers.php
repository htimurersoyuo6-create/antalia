<?php

namespace App\Support;

use App\Models\User;

class WebRtcIceServers
{
    /** Short-lived TURN credentials; never put the shared TURN secret in a page. */
    public static function forUser(User $user): array
    {
        $servers = [['urls' => ['stun:stun.l.google.com:19302']]];
        $secret = (string) config('services.webrtc.turn_secret', '');
        $urls = (array) config('services.webrtc.turn_urls', []);
        if ($secret !== '' && $urls !== []) {
            $username = (string) (now()->addMinutes(55)->timestamp . ':apo:' . $user->id . ':' . bin2hex(random_bytes(8)));
            $servers[] = [
                'urls' => array_values($urls),
                'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
            ];
        }
        return [
            'ice_servers' => $servers,
            'ice_transport_policy' => (bool) config('services.webrtc.relay_only', false) && count($servers) > 1 ? 'relay' : 'all',
        ];
    }
}
