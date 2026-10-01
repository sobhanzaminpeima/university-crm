<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'System Error' }}</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f8fafc; margin:0; padding:24px; }
        .wrap { max-width:820px; margin:40px auto; background:#fff; border:1px solid #fecaca; border-radius:14px; padding:20px; }
        h1 { margin:0 0 8px 0; color:#991b1b; font-size:24px; }
        p { color:#334155; line-height:1.7; }
        pre { background:#fff1f2; color:#7f1d1d; border:1px solid #fecdd3; border-radius:10px; padding:10px; white-space:pre-wrap; word-break:break-word; }
        a { color:#0f766e; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>{{ $title ?? 'System Error' }}</h1>
        <p>{{ $message ?? 'An unexpected error occurred.' }}</p>
        @if(!empty($details))
            <pre>{{ $details }}</pre>
        @endif
        <p><a href="javascript:history.back()">Go back</a></p>
    </div>
</body>
</html>

