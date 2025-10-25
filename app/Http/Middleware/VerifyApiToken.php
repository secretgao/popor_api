<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => '未提供访问令牌'
            ], 401);
        }
        
        // 验证令牌
        $user = $this->verifyToken($token);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '无效的访问令牌'
            ], 401);
        }
        
        // 将用户信息添加到请求中
        $request->attributes->set('auth_user', $user);
        
        return $next($request);
    }
    
    /**
     * 验证令牌
     *
     * @param string $token
     * @return object|null
     */
    private function verifyToken($token)
    {
        try {
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return null;
            }
            
            list($header, $payload, $signature) = $parts;
            
            // 验证签名
            $expectedSignature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, config('app.key'), true));
            
            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }
            
            // 解码载荷
            $payloadData = json_decode(base64_decode($payload), true);
            
            if (!$payloadData) {
                return null;
            }
            
            // 检查过期时间
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                return null;
            }
            
            return (object) $payloadData;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}
