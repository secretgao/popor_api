<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SqlLogServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 监听数据库查询事件
        DB::listen(function ($query) {
            $sql = $query->sql;
            $bindings = $query->bindings;
            $time = $query->time;
            
            // 替换SQL中的占位符
            foreach ($bindings as $binding) {
                $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
                $sql = preg_replace('/\?/', $value, $sql, 1);
            }
            
            // 记录SQL日志
            Log::channel('sql')->info('SQL查询', [
                'sql' => $sql,
                'time' => $time . 'ms',
                'bindings' => $bindings,
                'timestamp' => now()->toDateTimeString(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip()
            ]);
        });
    }
}
