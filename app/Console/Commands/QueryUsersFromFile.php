<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QueryUsersFromFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:query-from-file 
                            {file : 用户名文件路径}
                            {--batch-size=1000 : 每批处理的用户名数量}
                            {--output= : 输出文件路径}
                            {--method=batch : 查询方法 (batch|temp|exists)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从文件中读取用户名并查询数据库中的用户信息';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $batchSize = $this->option('batch-size');
        $outputPath = $this->option('output') ?: storage_path('app/user_query_results.csv');
        $method = $this->option('method');

        // 检查文件是否存在
        if (!file_exists($filePath)) {
            $this->error("文件不存在: $filePath");
            return 1;
        }

        // 读取用户名
        $usernames = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $totalUsernames = count($usernames);
        
        $this->info("总共需要查询的用户名数量: $totalUsernames");
        $this->info("使用查询方法: $method");
        $this->info("批处理大小: $batchSize");

        $startTime = microtime(true);

        switch ($method) {
            case 'temp':
                $results = $this->queryWithTempTable($usernames, $batchSize);
                break;
            case 'exists':
                $results = $this->queryWithExists($usernames, $batchSize);
                break;
            case 'batch':
            default:
                $results = $this->queryWithBatch($usernames, $batchSize);
                break;
        }

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        $this->info("查询完成！");
        $this->info("找到用户数量: " . count($results));
        $this->info("执行时间: {$executionTime}秒");

        // 保存结果
        $this->saveResults($results, $outputPath);
        $this->info("结果已保存到: $outputPath");

        // 显示统计信息
        $this->showStatistics($results);

        return 0;
    }

    /**
     * 使用临时表查询
     */
    private function queryWithTempTable($usernames, $batchSize)
    {
        $this->info("使用临时表方法查询...");
        
        DB::beginTransaction();
        
        try {
            // 创建临时表
            DB::statement('CREATE TEMP TABLE temp_usernames (username VARCHAR(255) PRIMARY KEY)');
            
            // 批量插入数据
            $totalBatches = ceil(count($usernames) / $batchSize);
            $progressBar = $this->output->createProgressBar($totalBatches);
            
            for ($batch = 0; $batch < $totalBatches; $batch++) {
                $startIndex = $batch * $batchSize;
                $batchUsernames = array_slice($usernames, $startIndex, $batchSize);
                
                foreach ($batchUsernames as $username) {
                    DB::table('temp_usernames')->insert(['username' => $username]);
                }
                
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $this->newLine();
            
            // 执行查询
            $results = DB::table('users as u')
                ->join('temp_usernames as t', 'u.username', '=', 't.username')
                ->select('u.username', 'u.created_at')
                ->orderBy('u.created_at', 'desc')
                ->get()
                ->toArray();
            
            DB::commit();
            
            return $results;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("临时表查询失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 使用 EXISTS 查询
     */
    private function queryWithExists($usernames, $batchSize)
    {
        $this->info("使用 EXISTS 方法查询...");
        
        $totalBatches = ceil(count($usernames) / $batchSize);
        $progressBar = $this->output->createProgressBar($totalBatches);
        $allResults = [];
        
        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $startIndex = $batch * $batchSize;
            $batchUsernames = array_slice($usernames, $startIndex, $batchSize);
            
            $existsConditions = [];
            foreach ($batchUsernames as $username) {
                $existsConditions[] = "u.username = ?";
            }
            
            $sql = "SELECT u.username, u.created_at FROM users u WHERE " . implode(' OR ', $existsConditions);
            
            $results = DB::select($sql, $batchUsernames);
            $allResults = array_merge($allResults, $results);
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        return $allResults;
    }

    /**
     * 使用分批 IN 查询
     */
    private function queryWithBatch($usernames, $batchSize)
    {
        $this->info("使用分批 IN 方法查询...");
        
        $totalBatches = ceil(count($usernames) / $batchSize);
        $progressBar = $this->output->createProgressBar($totalBatches);
        $allResults = [];
        
        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $startIndex = $batch * $batchSize;
            $batchUsernames = array_slice($usernames, $startIndex, $batchSize);
            
            $placeholders = str_repeat('?,', count($batchUsernames) - 1) . '?';
            $sql = "SELECT username, created_at FROM users WHERE username IN ($placeholders)";
            
            $results = DB::select($sql, $batchUsernames);
            $allResults = array_merge($allResults, $results);
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        return $allResults;
    }

    /**
     * 保存结果到文件
     */
    private function saveResults($results, $outputPath)
    {
        $file = fopen($outputPath, 'w');
        
        // 写入CSV头部
        fputcsv($file, ['username', 'created_at']);
        
        // 写入数据
        foreach ($results as $row) {
            if (is_object($row)) {
                fputcsv($file, [$row->username, $row->created_at]);
            } else {
                fputcsv($file, [$row['username'], $row['created_at']]);
            }
        }
        
        fclose($file);
    }

    /**
     * 显示统计信息
     */
    private function showStatistics($results)
    {
        if (empty($results)) {
            $this->warn("没有找到任何用户数据");
            return;
        }
        
        $createdDates = [];
        foreach ($results as $row) {
            $createdDates[] = is_object($row) ? $row->created_at : $row['created_at'];
        }
        
        $minDate = min($createdDates);
        $maxDate = max($createdDates);
        
        $this->newLine();
        $this->info("统计信息:");
        $this->info("最早创建时间: $minDate");
        $this->info("最晚创建时间: $maxDate");
        
        if ($minDate !== $maxDate) {
            $daysDiff = (strtotime($maxDate) - strtotime($minDate)) / 86400;
            $avgPerDay = round(count($results) / max(1, $daysDiff), 2);
            $this->info("平均每天创建用户数: $avgPerDay");
        }
    }
}
