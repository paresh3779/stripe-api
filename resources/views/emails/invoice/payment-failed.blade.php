<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Failed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #ef4444; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .notice { background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .details-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .details-row:last-child { border-bottom: none; }
        .label { color: #6b7280; }
        .value { font-weight: 600; color: #111827; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
        .button { display: inline-block; background: #ef4444; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Payment Failed</h1>
    </div>
    
    <div class="content">
        <p>Hi {{ $user->first_name ?? 'there' }},</p>
        
        <p>We were unable to process your payment. Please update your payment information to avoid service interruption.</p>
        
        <div class="notice">
            <strong>Action Required:</strong> Please update your payment method or retry the payment to continue your subscription.
        </div>
        
        <div class="details">
            <h3 style="margin-top: 0;">Payment Details</h3>
            <div class="details-row">
                <span class="label">Amount Due</span>
                <span class="value">{{ $currency }} {{ $amount }}</span>
            </div>
            @if($subscription)
            <div class="details-row">
                <span class="label">Subscription</span>
                <span class="value">{{ $subscription->product->name ?? 'Subscription' }}</span>
            </div>
            @endif
        </div>
        
        @if($hostedInvoiceUrl)
        <p style="text-align: center;">
            <a href="{{ $hostedInvoiceUrl }}" class="button">Update Payment & Retry</a>
        </p>
        @endif
        
        <p>If you continue to have issues, please contact our support team for assistance.</p>
    </div>
    
    <div class="footer">
        <p>This is an important notification regarding your account.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
