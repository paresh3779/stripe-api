<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Expired</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #ef4444; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .notice { background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Subscription Expired</h1>
    </div>
    
    <div class="content">
        <p>Hi {{ $user->first_name ?? 'there' }},</p>
        
        <p>Your subscription to <strong>{{ $product->name ?? 'our service' }}</strong> has expired.</p>
        
        <div class="notice">
            <strong>🔒 Access Revoked:</strong> Your access to premium features has been suspended. To continue using all features, please renew your subscription.
        </div>
        
        <p>We'd love to have you back! Renew now to regain access to all your favorite features.</p>
        
        <p style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/main/stripe-subscription-checkout/subscription" class="button">
                Renew Subscription
            </a>
        </p>
    </div>
    
    <div class="footer">
        <p>If you have any questions, please contact our support team.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
