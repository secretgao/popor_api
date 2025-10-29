<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryOptimizationService
{
    /**
     * 启用查询日志记录
     */
    public static function enableQueryLog(): void
    {
        DB::enableQueryLog();
    }

    /**
     * 获取查询日志
     */
    public static function getQueryLog(): array
    {
        return DB::getQueryLog();
    }

    /**
     * 记录查询性能
     */
    public static function logQueryPerformance(string $operation, array $queries): void
    {
        $totalQueries = count($queries);
        $totalTime = array_sum(array_column($queries, 'time'));
        
        Log::channel('sql')->info("Query Performance - {$operation}", [
            'total_queries' => $totalQueries,
            'total_time' => $totalTime,
            'queries' => $queries
        ]);
    }

    /**
     * 检测N+1查询问题
     */
    public static function detectNPlusOneQueries(array $queries, int $threshold = 5): array
    {
        $patterns = [];
        $queryCounts = [];
        
        foreach ($queries as $query) {
            $sql = $query['sql'];
            $table = self::extractTableName($sql);
            
            if ($table) {
                $queryCounts[$table] = ($queryCounts[$table] ?? 0) + 1;
            }
        }
        
        foreach ($queryCounts as $table => $count) {
            if ($count > $threshold) {
                $patterns[] = [
                    'table' => $table,
                    'query_count' => $count,
                    'potential_n_plus_one' => true
                ];
            }
        }
        
        return $patterns;
    }

    /**
     * 从SQL中提取表名
     */
    private static function extractTableName(string $sql): ?string
    {
        if (preg_match('/FROM\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * 优化关联查询
     */
    public static function optimizeRelations(Builder $query, array $relations): Builder
    {
        return $query->with($relations);
    }

    /**
     * 优化计数查询
     */
    public static function optimizeCounts(Builder $query, array $countRelations): Builder
    {
        return $query->withCount($countRelations);
    }

    /**
     * 优化分页查询
     */
    public static function optimizePagination(Builder $query, int $perPage = 15): Builder
    {
        return $query->paginate($perPage);
    }

    /**
     * 检查查询是否使用了索引
     */
    public static function checkIndexUsage(string $sql): bool
    {
        // 简单的索引使用检查
        $indexKeywords = ['PRIMARY', 'INDEX', 'KEY'];
        foreach ($indexKeywords as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 生成查询优化建议
     */
    public static function generateOptimizationSuggestions(array $queries): array
    {
        $suggestions = [];
        
        foreach ($queries as $query) {
            $sql = $query['sql'];
            $time = $query['time'];
            
            // 检查慢查询
            if ($time > 1000) { // 超过1秒
                $suggestions[] = [
                    'type' => 'slow_query',
                    'sql' => $sql,
                    'time' => $time,
                    'suggestion' => '考虑添加索引或优化查询条件'
                ];
            }
            
            // 检查SELECT *
            if (preg_match('/SELECT\s+\*\s+FROM/i', $sql)) {
                $suggestions[] = [
                    'type' => 'select_all',
                    'sql' => $sql,
                    'suggestion' => '避免使用SELECT *，只选择需要的字段'
                ];
            }
            
            // 检查N+1查询模式
            if (preg_match('/WHERE.*IN\s*\([^)]+\)/i', $sql)) {
                $suggestions[] = [
                    'type' => 'potential_n_plus_one',
                    'sql' => $sql,
                    'suggestion' => '考虑使用with()预加载关联数据'
                ];
            }
        }
        
        return $suggestions;
    }
}
