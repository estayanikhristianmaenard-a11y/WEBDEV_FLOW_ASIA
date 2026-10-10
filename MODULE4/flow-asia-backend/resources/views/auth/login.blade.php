<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flow Asia | Sign in</title>
    <link rel="stylesheet" href="{{ url('/m4/assets/css/variables.css') }}">
    <link rel="stylesheet" href="{{ url('/m4/assets/css/components.css') }}">
</head>
<body style="background:var(--bg);color:var(--ink);font-family:Arial,sans-serif;padding:8vh 24px">
    <main class="card" style="max-width:400px;margin:auto">
        <h1>Flow Asia</h1>
        <p>Sign in to your calendar dashboard.</p>
        <form method="post" action="{{ url('/login') }}">
            @csrf
            @if ($errors->any())
                <p role="alert">{{ $errors->first() }}</p>
            @endif
            <label for="email">Email</label>
            <input class="hr-search-input" style="display:block;width:100%;margin:8px 0 16px" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
            <label for="password">Password</label>
            <input class="hr-search-input" style="display:block;width:100%;margin:8px 0 16px" id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="btn btn-primary" type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
