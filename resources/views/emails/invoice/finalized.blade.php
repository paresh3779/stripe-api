<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice Ready for Payment</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #007bff 0%, #6610f2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Invoice Ready for Payment</h1>
    </div>
    
    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px;">Hello {{ $user->name ?? 'Customer' }},</p>
        
        <p>Your invoice has been finalized and is ready for payment.</p>
        
        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;">
            <h3 style="margin-top: 0; color: #007bff;">Invoice Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Invoice Number:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">{{ $invoiceNumber }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee;"><strong>Amount Due:</strong></td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right; font-size: 18px; color: #007bff;"><strong>{{ $currency }} {{ $amount }}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Due Date:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $dueDate }}</td>
                </tr>
                @if($subscription)
                <tr>
                    <td style="padding: 8px 0;"><strong>Subscription:</strong></td>
                    <td style="padding: 8px 0; text-align: right;">{{ $subscription->product?->name ?? 'Subscription' }}</td>
                </tr>
                @endif
            </table>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            @if($hostedInvoiceUrl)
            <a href="{{ $hostedInvoiceUrl }}" style="display: inline-block; background: #007bff; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 5px;">Pay Now</a>
            @endif
            @if($invoicePdfUrl)
            <a href="{{ $invoicePdfUrl }}" style="display: inline-block; background: #6c757d; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 5px;">Download PDF</a>
            @endif
        </div>
        
        <p style="color: #666; font-size: 14px;">Please ensure payment is made by the due date to avoid any service interruption.</p>
        
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        
        <p style="color: #999; font-size: 12px; text-align: center;">
            This email was sent by {{ config('app.name') }}.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </p>
    </div>
</body>
</html>
