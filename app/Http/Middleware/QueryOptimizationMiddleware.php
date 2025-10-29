<?php

namespace App\Http\Middleware;

use App\Services\QueryOptimizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryOptimizationMiddleware
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
        // 只在开发环境或API路由中启用
        if (!config('app.debug') || !$request->is('api/*')) {
            return $next($request);
        }

        // 启用查询日志
        QueryOptimizationService::enableQueryLog();
        
        $response = $next($request);
        
        // 分析查询性能
        $this->analyzeQueryPerformance($request);
        
        return $response;
    }

    /**
     * 分析查询性能
     */
    private function analyzeQueryPerformance(Request $request): void
    {
        $queries = QueryOptimizationService::getQueryLog();
        
        if (empty($queries)) {
            return;
        }

        // 记录查询性能
        QueryOptimizationService::logQueryPerformance(
            $request->method() . ' ' . $request->path(),
            $queries
        );

        // 检测N+1查询
        $nPlusOnePatterns = QueryOptimizationService::detectNPlusOneQueries($queries);
        
        if (!empty($nPlusOnePatterns)) {
            Log::warning('Potential N+1 Query Detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'patterns' => $nPlusOnePatterns,
                'total_queries' => count($queries)
            ]);
        }

        // 生成优化建议
        $suggestions = QueryOptimizationService::generateOptimizationSuggestions($queries);
        
        if (!empty($suggestions)) {
            Log::info('Query Optimization Suggestions', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'suggestions' => $suggestions
            ]);
        }
    }
}
