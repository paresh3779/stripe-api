<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Dispute {{ $isWon ? 'Resolved' : 'Update' }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, {{ $isWon ? '#28a745' : '#6c757d' }} 0%, {{ $isWon ? '#20c997' : '#495057' }} 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Payment Dispute {{ $isWon ? 'Resolved' : 'Update' }}</h1>
    </div>
    
    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px;">Hello {{ $user->name ?? 'Customer' }},</p>
        
        @if($isWon)
        <p>Great news! The dispute for your payment has been resolved in your favor.</p>
        @else
        <p>The dispute for your payment has been closed. Here are the details.</p>
        @endif
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid {{ $isWon ? '#28a745' : '#6c757d' }};">
            <h3 style="margin-top: 0; color: {{ $isWon ? '#28a745' : '#6c757d' }};">Dispute Resolution</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Transaction ID:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{ $transactionId }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Disputed Amount:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{ $currency }} {{ $disputeAmount }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Status:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">
                        <span style="background: {{ $isWon ? '#28a745' : '#6c757d' }}; color: white; padding: 4px 12px; border-radius: 4px; font-size: 14px;">
                            {{ ucfirst($disputeStatus) }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        
        @if($isWon)
        <div style="background: #d4edda; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0; color: #155724;"><strong>✓ Dispute Won</strong><br>
            <small>No further action is required from you.</small></p>
        </div>
        @else
        <div style="background: #e2e3e5; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0; color: #383d41;"><strong>ℹ️ Dispute Closed</strong><br>
            <small>If you have questions about the outcome, please contact our support team.</small></p>
        </div>
        @endif
        
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        
        <p style="color: #999; font-size: 12px; text-align: center;">
            This email was sent by {{ config('app.name') }}.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
