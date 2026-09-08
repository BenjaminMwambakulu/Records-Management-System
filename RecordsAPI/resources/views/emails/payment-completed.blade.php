<x-mail::message>
# Payment Confirmed

Your payment has been successfully processed.

**Amount:** {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
**For:** {{ $payableTitle }}
**Reference:** {{ $payment->tx_ref }}
**Date:** {{ $payment->paid_at->format('M d, Y H:i') }}

<x-mail::button :url="url('/payments/'.$payment->id)">
View Receipt
</x-mail::button>

Thank you for your payment!<br>
{{ config('app.name') }}
</x-mail::message>
