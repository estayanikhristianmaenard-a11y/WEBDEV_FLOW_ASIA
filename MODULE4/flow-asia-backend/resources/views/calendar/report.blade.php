<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} · Flow Asia</title>
    <link rel="stylesheet" href="{{ route('m4.assets', ['path' => 'css/variables.css']) }}">
    <link rel="stylesheet" href="{{ route('m4.assets', ['path' => 'css/components.css']) }}">
    <style>body{display:block;padding:24px}main{max-width:800px;margin:auto}h1{margin:16px 0}.report-body{white-space:pre-wrap;overflow-wrap:anywhere;margin:24px 0}</style>
</head>
<body>
<main>
    <a class="btn btn-secondary" href="{{ route('m4.dashboard') }}">Back to calendar</a>
    <h1>{{ $report['title'] }}</h1>
    <p>{{ $employee->first_name }} {{ $employee->last_name }} · {{ $employee->employee_no }} · {{ $report['date'] }}</p>
    <p>Submitted report · Read-only</p>
    <div class="report-body">{{ $report['body'] }}</div>
</main>
</body>
</html>
