<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Dispute Notification</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Payment Dispute Notification</h1>
    </div>
    
    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px;">Hello {{ $user->name ?? 'Customer' }},</p>
        
        <p>We've received a dispute notification regarding one of your payments. We wanted to keep you informed.</p>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;">
            <h3 style="margin-top: 0; color: #dc3545;">Dispute Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Transaction ID:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{ $transactionId }}</td>
                </tr>
                @if($product)
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Product:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{ $product->name }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Disputed Amount:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right; font-size: 18px; color: #dc3545;"><strong>{{ $currency }} {{ $disputeAmount }}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Reason:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $disputeReason }}</td>
                </tr>
            </table>
        </div>
        
        <div style="background: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0; color: #721c24;"><strong>⚠️ What happens next?</strong><br>
            <small>We're investigating this dispute. If you have any information that could help, please contact our support team immediately.</small></p>
        </div>
        
        <p style="color: #666; font-size: 14px;">If you believe this dispute is in error or have questions, please contact our support team as soon as possible.</p>
        
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        
        <p style="color: #999; font-size: 12px; text-align: center;">
            This email was sent by {{ config('app.name') }}.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
