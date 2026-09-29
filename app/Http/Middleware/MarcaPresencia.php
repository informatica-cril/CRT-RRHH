<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MarcaPresencia
{
    private const PERIODE_SEG = 60;

    public function handle(Request $request, Closure $next)
    {
        $resposta = $next($request);

        $user = $request->user();
        if ($user) {
            $clau = 'presencia:batec:' . $user->id;
            if (! Cache::has($clau)) {
                Cache::put($clau, 1, self::PERIODE_SEG);
                $user->forceFill(['last_chat_heartbeat' => now()])->saveQuietly();
            }
        }

        return $resposta;
    }
}
