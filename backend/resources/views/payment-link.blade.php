<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оплата — {{ $link->description }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-md p-8">

        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
            <h1 class="text-xl font-semibold text-gray-900">{{ $link->description }}</h1>
            <p class="text-3xl font-bold text-gray-900 mt-3">
                {{ number_format($link->amount / 100, 2, '.', ' ') }} {{ $link->currency }}
            </p>
        </div>

        @if($link->expires_at)
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-700">
            Ссылка действительна до {{ $link->expires_at->format('d.m.Y H:i') }}
        </div>
        @endif

        <form method="POST" action="/pay/{{ $link->token }}">
            @csrf
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors text-lg">
                Оплатить
            </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-4">
            Безопасная оплата через {{ ucfirst($link->provider) }}
        </p>
    </div>
</body>
</html>
