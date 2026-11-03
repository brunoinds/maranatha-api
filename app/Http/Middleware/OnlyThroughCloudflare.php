<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * maranatha.top fica atrás do Cloudflare. Sem esta trava, quem souber o IP da VPS acessa a API direto,
 * sem o WAF e a proteção contra DDoS do Cloudflare.
 *
 * O kamal-proxy grava em X-Forwarded-For o IP de quem conectou nele (com TLS ele não repassa o
 * cabeçalho do cliente), então o último valor é o próprio Cloudflare quando o tráfego vem por ele.
 * Sem o cabeçalho, a requisição não passou pelo proxy (healthcheck do kamal-proxy, chamadas internas).
 */
class OnlyThroughCloudflare
{
    // https://www.cloudflare.com/ips-v4 e /ips-v6 (conferido em 2026-11-03)
    private const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.only_cloudflare')) {
            return $next($request);
        }

        $forwarded = (string) $request->server('HTTP_X_FORWARDED_FOR', '');
        if ($forwarded === '') {
            return $next($request);
        }

        $edge = trim((string) last(explode(',', $forwarded)));
        if (! IpUtils::checkIp($edge, self::CLOUDFLARE_RANGES)) {
            abort(403);
        }

        return $next($request);
    }
}
