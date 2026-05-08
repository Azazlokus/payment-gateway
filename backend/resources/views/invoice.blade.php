<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
        .brand { font-size: 22px; font-weight: bold; color: #2563eb; }
        .brand-sub { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .invoice-meta { text-align: right; }
        .invoice-meta .label { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; }
        .invoice-meta .value { font-size: 13px; font-weight: 600; }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 20px 0; }
        .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #9ca3af; margin-bottom: 12px; }
        .grid-2 { display: table; width: 100%; margin-bottom: 30px; }
        .col { display: table-cell; width: 50%; vertical-align: top; }
        .field-label { font-size: 10px; color: #6b7280; margin-bottom: 2px; }
        .field-value { font-size: 13px; color: #111827; }
        .amount-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .amount-label { font-size: 11px; color: #0369a1; margin-bottom: 6px; }
        .amount-value { font-size: 32px; font-weight: bold; color: #0369a1; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { font-size: 10px; text-transform: uppercase; color: #9ca3af; padding: 8px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        td { padding: 10px 12px; font-size: 12px; border-bottom: 1px solid #f3f4f6; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-succeeded { background: #d1fae5; color: #065f46; }
        .badge-pending   { background: #fef3c7; color: #92400e; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-refunded  { background: #e0e7ff; color: #3730a3; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <div class="brand">Payment Gateway</div>
            <div class="brand-sub">Квитанция об оплате</div>
        </div>
        <div class="invoice-meta">
            <div class="label">Квитанция №</div>
            <div class="value">{{ strtoupper(substr($payment->id()->toString(), -8)) }}</div>
            <div style="margin-top:8px" class="label">Дата</div>
            <div class="value">{{ now()->format('d.m.Y') }}</div>
        </div>
    </div>

    <hr class="divider">

    <div class="amount-box">
        <div class="amount-label">Сумма платежа</div>
        <div class="amount-value">
            {{ number_format($payment->amount()->amount() / 100, 2, '.', ' ') }}
            {{ $payment->amount()->currency()->value }}
        </div>
    </div>

    <p class="section-title">Детали платежа</p>

    <table>
        <thead>
            <tr>
                <th>Параметр</th>
                <th>Значение</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Описание</td>
                <td>{{ $payment->description() }}</td>
            </tr>
            <tr>
                <td>Внутренний ID</td>
                <td style="font-family: monospace; font-size: 11px;">{{ $payment->id()->toString() }}</td>
            </tr>
            @if($payment->externalId())
            <tr>
                <td>ID провайдера</td>
                <td style="font-family: monospace; font-size: 11px;">{{ $payment->externalId()->toString() }}</td>
            </tr>
            @endif
            <tr>
                <td>Провайдер</td>
                <td>{{ ucfirst($payment->provider()) }}</td>
            </tr>
            <tr>
                <td>Статус</td>
                <td>
                    <span class="badge badge-{{ strtolower($payment->status()->value) }}">
                        {{ $payment->status()->value }}
                    </span>
                </td>
            </tr>
            @if($payment->refundedAmount() > 0)
            <tr>
                <td>Возвращено</td>
                <td>{{ number_format($payment->refundedAmount() / 100, 2, '.', ' ') }} {{ $payment->amount()->currency()->value }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Сформировано {{ now()->format('d.m.Y H:i:s') }} &bull;
        Документ сформирован автоматически и действителен без подписи.
    </div>

</body>
</html>
