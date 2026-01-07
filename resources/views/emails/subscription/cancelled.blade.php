<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Cancelled</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f59e0b; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .notice { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Subscription Cancelled</h1>
    </div>
    
    <div class="content">
        <p>Hi {{ $user->first_name ?? 'there' }},</p>
        
        <p>We're sorry to see you go. Your subscription to <strong>{{ $product->name ?? 'our service' }}</strong> has been cancelled.</p>
        
        @if($cancelAtPeriodEnd && $accessUntil)
        <div class="notice">
            <strong>📅 Important:</strong> You will continue to have access to your subscription benefits until <strong>{{ $accessUntil }}</strong>. After this date, your access will be revoked.
        </div>
        @else
        <div class="notice">
            <strong>ℹ️ Note:</strong> Your subscription has been cancelled immediately and your access has been revoked.
        </div>
        @endif
        
        <p>We'd love to have you back! If you change your mind, you can resubscribe at any time.</p>
        
        <p style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/main/stripe-subscription-checkout/subscription" class="button">
                Resubscribe
            </a>
        </p>
        
        <p>If you have any feedback about why you cancelled, we'd love to hear from you.</p>
    </div>
    
    <div class="footer">
        <p>Thank you for being a customer.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
