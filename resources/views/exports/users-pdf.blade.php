<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        .header h1 {
            font-size: 22px;
            margin-bottom: 4px;
        }
        .header .date {
            font-size: 10px;
            color: #666;
        }
        .summary {
            margin-bottom: 20px;
            padding: 10px 15px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        .summary h3 {
            font-size: 13px;
            margin-bottom: 6px;
        }
        .summary .stat-item {
            display: inline-block;
            margin-right: 20px;
            font-size: 11px;
        }
        .summary .stat-value {
            font-weight: bold;
            font-size: 14px;
        }
        .filters {
            margin-bottom: 15px;
            font-size: 10px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        thead th {
            background-color: #333;
            color: #fff;
            padding: 8px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tbody td {
            padding: 6px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 10px;
        }
        .role-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .role-customer { background-color: #e8f5e9; color: #2e7d32; }
        .role-vendor { background-color: #e3f2fd; color: #1565c0; }
        .role-admin { background-color: #f3e5f5; color: #7b1fa2; }
        .role-super_admin { background-color: #fce4ec; color: #c62828; }
        .role-influencer { background-color: #fff3e0; color: #e65100; }
        .role-field_agent { background-color: #e0f2f1; color: #00695c; }
        .role-marketer { background-color: #fff8e1; color: #f57f17; }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>User Report</h1>
        <div class="date">Generated on {{ $generatedAt }}</div>
    </div>

    <div class="summary">
        <h3>Summary</h3>
        <div>
            <span class="stat-item">
                <span class="stat-value">{{ $totalUsers }}</span> Total Users
            </span>
            @foreach($roleBreakdown->sortKeys() as $role => $count)
                <span class="stat-item">
                    {{ ucwords(str_replace('_', ' ', $role)) }}: <strong>{{ $count }}</strong>
                </span>
            @endforeach
        </div>
    </div>

    @if($roleFilter || $searchFilter)
        <div class="filters">
            Active filters:
            @if($roleFilter)
                Role: <strong>{{ ucwords(str_replace(['_', ','], [' ', ', '], $roleFilter)) }}</strong>
            @endif
            @if($searchFilter)
                {{ $roleFilter ? ' | ' : '' }}Search: <strong>"{{ $searchFilter }}"</strong>
            @endif
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Joined</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $index => $user)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone ?? '-' }}</td>
                    <td>
                        <span class="role-badge role-{{ $user->role }}">
                            {{ ucwords(str_replace('_', ' ', $user->role)) }}
                        </span>
                    </td>
                    <td>{{ $user->created_at->format('M j, Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Surprise Moi &mdash; Confidential User Report
    </div>
</body>
</html>
