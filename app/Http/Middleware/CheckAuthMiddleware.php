<?php

namespace App\Http\Middleware;

use Closure;

class CheckAuthMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $uid   = $request->header('x-consumer-custom-id');
        $email = $request->header('x-consumer-username');
        if (!is_numeric($uid) || $uid === 0 || empty($email)) {
            return response('Không thể xác thực tài khoản.', 401);
        }
        $request->attributes->add(['email' => $email]);
        $request->attributes->add(['uid' => (int) $uid]);

        return $next($request);
    }
}
