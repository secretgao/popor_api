<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - API System Info</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section { margin-bottom: 30px; }
        .section h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .info-card { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
        .info-card h3 { margin-top: 0; color: #007bff; }
        .info-item { margin: 8px 0; }
        .info-label { font-weight: bold; color: #555; }
        .info-value { color: #333; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 0.9em; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 {{ config('app.name') }} API System Information</h1>
        
        @if(isset($error))
            <div class="error">
                <strong>Error:</strong> {{ $error }}
            </div>
        @endif

        @if(isset($dbStatus) && $dbStatus === 'Connected')
            <div class="success">
                ✅ Database connection successful!
            </div>
        @endif

        <!-- 统计数据 -->
        @if(isset($stats))
        <div class="section">
            <h2>📊 System Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">{{ $stats['total_users'] ?? 0 }}</div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">{{ $stats['total_courses'] ?? 0 }}</div>
                    <div class="stat-label">Total Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">{{ $stats['total_invoices'] ?? 0 }}</div>
                    <div class="stat-label">Total Invoices</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">{{ $stats['active_users'] ?? 0 }}</div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">{{ $stats['pending_invoices'] ?? 0 }}</div>
                    <div class="stat-label">Pending Invoices</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">{{ $stats['paid_invoices'] ?? 0 }}</div>
                    <div class="stat-label">Paid Invoices</div>
                </div>
            </div>
        </div>
        @endif

        <!-- 环境信息 -->
        <div class="section">
            <h2>🔧 Environment Information</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>Application</h3>
                    @if(isset($envInfo))
                        @foreach(['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL'] as $key)
                            <div class="info-item">
                                <span class="info-label">{{ $key }}:</span>
                                <span class="info-value">{{ $envInfo[$key] ?? 'N/A' }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
                
                <div class="info-card">
                    <h3>Database</h3>
                    @if(isset($envInfo))
                        @foreach(['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DATABASE_URL'] as $key)
                            <div class="info-item">
                                <span class="info-label">{{ $key }}:</span>
                                <span class="info-value">{{ $envInfo[$key] ?? 'N/A' }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
                
                <div class="info-card">
                    <h3>System</h3>
                    @if(isset($envInfo))
                        @foreach(['PHP_VERSION', 'LARAVEL_VERSION'] as $key)
                            <div class="info-item">
                                <span class="info-label">{{ $key }}:</span>
                                <span class="info-value">{{ $envInfo[$key] ?? 'N/A' }}</span>
                            </div>
                        @endforeach
                    @endif
                    @if(isset($systemInfo))
                        @foreach($systemInfo as $key => $value)
                            <div class="info-item">
                                <span class="info-label">{{ $key }}:</span>
                                <span class="info-value">{{ $value }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <!-- 数据库信息 -->
        @if(isset($dbVersion))
        <div class="section">
            <h2>🗄️ Database Information</h2>
            <div class="info-card">
                <h3>Database Status</h3>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value">{{ $dbStatus ?? 'Unknown' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Version:</span>
                    <span class="info-value">{{ $dbVersion }}</span>
                </div>
            </div>
        </div>
        @endif

        <!-- 表列表 -->
        @if(isset($tables) && count($tables) > 0)
        <div class="section">
            <h2>📋 Database Tables</h2>
            <div class="info-card">
                <p><strong>Total Tables:</strong> {{ count($tables) }}</p>
                <div style="max-height: 200px; overflow-y: auto;">
                    @foreach($tables as $table)
                        <div style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            {{ $table->table_name }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- 用户数据 -->
        @if(isset($users) && count($users) > 0)
        <div class="section">
            <h2>👥 Users Data (First 10)</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Active</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users->take(10) as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->username ?? 'N/A' }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role ?? 'N/A' }}</td>
                            <td>{{ $user->is_active ? 'Yes' : 'No' }}</td>
                            <td>{{ $user->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- 课程数据 -->
        @if(isset($courses) && count($courses) > 0)
        <div class="section">
            <h2>📚 Courses Data (First 10)</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($courses->take(10) as $course)
                        <tr>
                            <td>{{ $course->id }}</td>
                            <td>{{ $course->name }}</td>
                            <td>{{ Str::limit($course->description, 50) }}</td>
                            <td>{{ $course->price }}</td>
                            <td>{{ $course->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- 账单数据 -->
        @if(isset($invoices) && count($invoices) > 0)
        <div class="section">
            <h2>💰 Invoices Data (First 10)</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student ID</th>
                        <th>Course ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Year Month</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices->take(10) as $invoice)
                        <tr>
                            <td>{{ $invoice->id }}</td>
                            <td>{{ $invoice->student_id }}</td>
                            <td>{{ $invoice->course_id }}</td>
                            <td>{{ $invoice->amount }}</td>
                            <td>{{ $invoice->status }}</td>
                            <td>{{ $invoice->year_month ?? 'N/A' }}</td>
                            <td>{{ $invoice->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- 表结构信息 -->
        @if(isset($tableStructures) && count($tableStructures) > 0)
        <div class="section">
            <h2>🏗️ Table Structures</h2>
            @foreach($tableStructures as $tableName => $columns)
                @if(count($columns) > 0)
                <div class="info-card" style="margin-bottom: 20px;">
                    <h3>Table: {{ $tableName }}</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Column Name</th>
                                <th>Data Type</th>
                                <th>Nullable</th>
                                <th>Default</th>
                                <th>Max Length</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($columns as $column)
                                <tr>
                                    <td>{{ $column->column_name }}</td>
                                    <td>{{ $column->data_type }}</td>
                                    <td>{{ $column->is_nullable === 'YES' ? 'Yes' : 'No' }}</td>
                                    <td>{{ $column->column_default ?? 'N/A' }}</td>
                                    <td>{{ $column->character_maximum_length ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            @endforeach
        </div>
        @endif

        <div style="text-align: center; margin-top: 30px; color: #666;">
            <p>Generated at: {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
