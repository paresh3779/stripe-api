<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Confirmed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
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
        <h1>🎉 Welcome!</h1>
        <p>Your subscription is now active</p>
    </div>
    
    <div class="content">
        <p>Hi {{ $user->first_name ?? 'there' }},</p>
        
        <p>Thank you for subscribing to <strong>{{ $product->name ?? 'our service' }}</strong>! Your subscription is now active and you have full access to all features.</p>
        
        <div class="details">
            <h3 style="margin-top: 0;">Subscription Details</h3>
            <div class="details-row">
                <span class="label">Plan</span>
                <span class="value">{{ $product->name ?? 'Subscription' }}</span>
            </div>
            <div class="details-row">
                <span class="label">Amount</span>
                <span class="value">{{ $currency }} {{ $amount }}/{{ $interval }}</span>
            </div>
            <div class="details-row">
                <span class="label">Next Billing Date</span>
                <span class="value">{{ $nextBillingDate ?? 'N/A' }}</span>
            </div>
        </div>
        
        <p>You can manage your subscription at any time from your account settings.</p>
        
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
