<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Receipt</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .details-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .details-row:last-child { border-bottom: none; }
        .label { color: #6b7280; }
        .value { font-weight: 600; color: #111827; }
        .total-row { background: #f3f4f6; padding: 15px; border-radius: 6px; margin-top: 10px; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
        .button-secondary { background: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Payment Received</h1>
        <p>Thank you for your payment</p>
    </div>
    
    <div class="content">
        <p>Hi {{ $user->first_name ?? 'there' }},</p>
        
        <p>We've received your payment. Here's your receipt:</p>
        
        <div class="details">
            <h3 style="margin-top: 0;">Receipt Details</h3>
            @if($invoiceNumber)
            <div class="details-row">
                <span class="label">Invoice Number</span>
                <span class="value">#{{ $invoiceNumber }}</span>
            </div>
            @endif
            <div class="details-row">
                <span class="label">Date Paid</span>
                <span class="value">{{ $paidAt ?? now()->format('F j, Y') }}</span>
            </div>
            @if($subscription)
            <div class="details-row">
                <span class="label">Description</span>
                <span class="value">{{ $subscription->product->name ?? 'Subscription' }}</span>
            </div>
            @endif
            <div class="total-row">
                <div class="details-row" style="border: none; margin: 0; padding: 0;">
                    <span class="label" style="font-weight: 600;">Total Paid</span>
                    <span class="value" style="font-size: 20px; color: #10b981;">{{ $currency }} {{ $amount }}</span>
                </div>
            </div>
        </div>
        
        <p style="text-align: center;">
            @if($invoicePdfUrl)
            <a href="{{ $invoicePdfUrl }}" class="button">Download PDF</a>
            @endif
            @if($hostedInvoiceUrl)
            <a href="{{ $hostedInvoiceUrl }}" class="button button-secondary">View Invoice</a>
            @endif
        </p>
    </div>
    
    <div class="footer">
        <p>This is an automated receipt for your records.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
