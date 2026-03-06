<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SELG Scanner' }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f6f8; color: #1f2937; }
        .topbar { background: #0f172a; color: #fff; padding: 12px 20px; }
        .topbar a { color: #fff; text-decoration: none; margin-right: 16px; }
        .container { max-width: 980px; margin: 24px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; text-align: left; padding: 10px 8px; }
        th { background: #f8fafc; }
        input, select { width: 100%; max-width: 420px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; }
        .btn { border: 0; border-radius: 6px; padding: 8px 12px; text-decoration: none; display: inline-block; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-muted { background: #64748b; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .status { background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 10px; border-radius: 6px; margin-bottom: 12px; }
        .error { color: #b91c1c; margin-top: 6px; font-size: 14px; }
        .mb-12 { margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="topbar">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <a href="{{ route('positions.index') }}">Positions</a>
        <a href="{{ route('candidates.index') }}">Candidates</a>
    </div>

    <main class="container">
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
