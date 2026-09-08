<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            line-height: 1.6;
            color: #1b1b18;
            margin: 0;
            padding: 0;
            background-color: #e5e1ec;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 40px;
            background-color: #ffffff;
            border: 1px solid #e3e3e0;
            border-radius: 8px;
        }

        .header {
            margin-bottom: 30px;
            text-align: center;
        }

        .logo {
            color: #f53003;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
        }

        h1 {
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        p {
            margin-bottom: 20px;
            color: #706f6c;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #110b79;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            margin-top: 10px;
        }

        .details {
            background-color: #f5f5f4;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .details p {
            margin-bottom: 8px;
            color: #1b1b18;
        }

        .details p:last-child {
            margin-bottom: 0;
        }

        .footer {
            margin-top: 40px;
            font-size: 12px;
            color: #a1a09a;
            text-align: center;
        }

        img {
            height: 100px;
            width: auto;
        }

        .qr-code {
            text-align: center;
            margin: 25px 0;
        }

        .qr-code img {
            height: 200px;
            width: 200px;
            border: 1px solid #e3e3e0;
            border-radius: 8px;
        }

        .qr-code p {
            font-size: 12px;
            color: #a1a09a;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="{{ config('app.frontend_url', '/') }}" class="logo">
                <img src="https://i.ibb.co/Q32VZdCj/csit-logo.png" alt="csit logo">
            </a>
        </div>

        <h1>Payment Confirmed</h1>

        <p>Hello,</p>

        <p>Your payment has been successfully processed for <strong>{{ $payableTitle }}</strong>.</p>

        <div class="details">
            <p><strong>Amount:</strong> {{ number_format($payment->amount, 2) }} {{ $payment->currency }}</p>
            <p><strong>Reference:</strong> {{ $payment->tx_ref ?? $payment->provider_reference ?? 'N/A' }}</p>
            <p><strong>Date:</strong> {{ $payment->paid_at->format('M d, Y H:i') }}</p>
        </div>

        <p>You are now registered for this event. Please show your QR code at the event to check in.</p>

        @if($qrCodeHash)
        <div class="qr-code">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrCodeHash) }}" alt="Event QR Code">
            <p>Scan this QR code at the event to check in</p>
        </div>
        @endif

        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url', '/') }}/events/{{ $eventId }}" class="button">View Event</a>
        </div>

        <p style="margin-top: 30px;">If you have any questions, feel free to reach out to the <a href="mailto:{{ config('admin.email', 'admin@must.ac.mw') }}">Support Team</a>.</p>

        <div class="footer">
            &copy; {{ date('Y') }} MUST CSIT Society. All rights reserved.
        </div>
    </div>
</body>
</html>
