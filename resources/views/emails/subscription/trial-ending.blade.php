<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Trial is Ending Soon</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, {{ $daysRemaining <= 1 ? '#dc3545' : '#ffc107' }} 0%, {{ $daysRemaining <= 1 ? '#fd7e14' : '#fd7e14' }} 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">
            @if($daysRemaining <= 1)
                ⚠️ Your Trial Ends Tomorrow!
            @else
                Your Trial Ends in {{ $daysRemaining }} Days
            @endif
        </h1>
    </div>
    
    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px;">Hello {{ $user->name ?? 'there' }},</p>
        
        @if($daysRemaining <= 1)
        <p><strong>This is your final reminder!</strong> Your free trial of {{ $product->name ?? 'our service' }} ends tomorrow.</p>
        @else
        <p>Just a friendly reminder that your free trial of {{ $product->name ?? 'our service' }} will end soon.</p>
        @endif
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid {{ $daysRemaining <= 1 ? '#dc3545' : '#ffc107' }};">
            <h3 style="margin-top: 0; color: #333;">Trial Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Plan:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{ $product->name ?? 'Subscription' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Trial Ends:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{ $trialEndDate }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>After Trial:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $currency }} {{ $amount }}/{{ $interval }}</td>
                </tr>
            </table>
        </div>
        
        <div style="background: {{ $daysRemaining <= 1 ? '#f8d7da' : '#fff3cd' }}; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0; color: {{ $daysRemaining <= 1 ? '#721c24' : '#856404' }};">
                <strong>What happens next?</strong><br>
                <small>After your trial ends, your payment method will be charged {{ $currency }} {{ $amount }} for your first {{ $interval }} of service. You can cancel anytime before the trial ends to avoid being charged.</small>
            </p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.frontend_url') }}/main/subscriptions" style="display: inline-block; background: #28a745; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 5px;">Keep My Subscription</a>
            <a href="{{ config('app.frontend_url') }}/main/subscriptions" style="display: inline-block; background: #6c757d; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 5px;">Manage Subscription</a>
        </div>
        
        <p style="color: #666; font-size: 14px;">If you have any questions, please contact our support team.</p>
        
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        
        <p style="color: #999; font-size: 12px; text-align: center;">
            This email was sent by {{ config('app.name') }}.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
