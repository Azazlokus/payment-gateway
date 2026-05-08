<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ссылка недействительна</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-md p-8 text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>
        <h1 class="text-xl font-semibold text-gray-900 mb-2">Ссылка недействительна</h1>
        <p class="text-gray-500 text-sm">
            @if($link->isExpired())
                Срок действия ссылки истёк.
            @else
                Ссылка уже была использована.
            @endif
        </p>
        <p class="text-gray-400 text-xs mt-4">Обратитесь к отправителю для получения новой ссылки.</p>
    </div>
</body>
</html>
