<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оплата принята</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-md p-8 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-xl font-semibold text-gray-900 mb-2">Оплата принята</h1>
        <p class="text-gray-500 text-sm">{{ $link->description }}</p>
        <p class="text-2xl font-bold text-gray-900 mt-3">
            {{ number_format($link->amount / 100, 2, '.', ' ') }} {{ $link->currency }}
        </p>
        <p class="text-gray-400 text-xs mt-6">Спасибо за оплату. Статус обновится в течение нескольких минут.</p>
    </div>
</body>
</html>
