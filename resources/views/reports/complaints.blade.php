<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 30px;
            color: #222;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 28px;
            color: #003366;
            margin: 0;
        }

        .subtitle {
            text-align: center;
            font-size: 13px;
            color: #444;
            margin-bottom: 20px;
        }

        .section-title {
            color: #003366;
            font-size: 20px;
            margin-top: 35px;
            margin-bottom: 10px;
            border-left: 4px solid #003366;
            padding-left: 8px;
        }

        .summary-box {
            padding: 15px;
            border: 1px solid #003366;
            background: #f1f5fa;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .stat-block {
            margin: 10px 0;
        }

        .stat-label {
            font-weight: bold;
            color: #003366;
        }

        pre.chart {
            background: #eef2f7;
            border: 1px solid #ccd4e0;
            padding: 12px;
            border-radius: 6px;
            font-size: 11px;
            white-space: pre-wrap;
            margin-top: 10px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Complaints Report</h1>
</div>

<div class="subtitle">
    Reporting period: <strong>{{ $filters['from'] }}</strong> → <strong>{{ $filters['to'] }}</strong><br>
    Governorate: <strong>{{ $filters['governorate_name'] }}</strong> —
    Department: <strong>{{ $filters['department_name'] }}</strong>
</div>

<h2 class="section-title">Summary Overview</h2>

<div class="summary-box">
    <div class="stat-block"><span class="stat-label">Total Complaints:</span> {{ $total }}</div>
    <div class="stat-block"><span class="stat-label">New:</span> {{ $by_status['new'] }}</div>
    <div class="stat-block"><span class="stat-label">In Progress:</span> {{ $by_status['in_progress'] }}</div>
    <div class="stat-block"><span class="stat-label">Resolved:</span> {{ $by_status['resolved'] }}</div>
    <div class="stat-block"><span class="stat-label">Rejected:</span> {{ $by_status['rejected'] }}</div>
    <div class="stat-block"><span class="stat-label">Needs Update:</span> {{ $by_status['needs_update'] }}</div>
</div>

<h2 class="section-title">Analytical Notes</h2>

<p style="font-size:14px; line-height:1.7;">
    This report provides a comprehensive overview of complaint activities within the selected period.
    The figures above illustrate the overall distribution of complaints across different workflow statuses.
    Higher counts in <strong>New</strong> or <strong>In Progress</strong> may indicate increased load or pending actions,
    while a high <strong>Resolved</strong> count indicates effective closure of issues.
</p>

<p style="font-size:14px; line-height:1.7;">
    Governorate <strong>{{ $filters['governorate_name'] }}</strong> and Department <strong>{{ $filters['department_name'] }}</strong>
    were included in filtering, providing a focused view of activity in that sector.
</p>

<h2 class="section-title">Status Distribution Chart</h2>

<pre class="chart">
@php
$max = max(array_values($by_status));
foreach ($by_status as $label => $count) {
    $bar = str_repeat("█", ($count == 0 ? 0 : round(($count / $max) * 40)));
    echo strtoupper(str_replace('_',' ',$label)) . " | " . $bar . " ($count)\n";
}
@endphp
</pre>

<div class="footer">
    Report generated on: {{ now()->format('Y-m-d H:i') }} — Complaint Management System
</div>

</body>
</html>
