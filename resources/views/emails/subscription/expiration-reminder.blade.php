<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Expiring Soon</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f59e0b; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .countdown { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .countdown-number { font-size: 48px; font-weight: bold; color: #f59e0b; }
        .countdown-label { color: #6b7280; }
        .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .details-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .details-row:last-child { border-bottom: none; }
        .label { color: #6b7280; }
        .value { font-weight: 600; color: #111827; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>⏰ Subscription Expiring Soon</h1>
    </div>
    
    <div class="content">
        <p>Hi {{ $user->first_name ?? 'there' }},</p>
        
        <p>Your subscription to <strong>{{ $product->name ?? 'our service' }}</strong> is expiring soon.</p>
        
        <div class="countdown">
            <div class="countdown-number">{{ $daysRemaining }}</div>
            <div class="countdown-label">{{ $daysRemaining === 1 ? 'Day' : 'Days' }} Remaining</div>
        </div>
        
        <div class="details">
            <h3 style="margin-top: 0;">Renewal Details</h3>
            <div class="details-row">
                <span class="label">Expiration Date</span>
                <span class="value">{{ $expirationDate ?? 'N/A' }}</span>
            </div>
            <div class="details-row">
                <span class="label">Renewal Amount</span>
                <span class="value">{{ $currency }} {{ $renewalAmount }}</span>
            </div>
        </div>
        
        <p>To ensure uninterrupted access, please make sure your payment method is up to date.</p>
        
        <p style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/main/stripe-subscription-checkout/subscription" class="button">
                Manage Subscription
            </a>
        </p>
    </div>
    
    <div class="footer">
        <p>If you have any questions, please contact our support team.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
