<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Refund Processed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .details-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .details-row:last-child { border-bottom: none; }
        .label { color: #6b7280; }
        .value { font-weight: 600; color: #111827; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>💰 Refund Processed</h1>
    </div>
    
    <div class="content">
        <p>Hi {{ $user->first_name ?? 'there' }},</p>
        
        <p>Your refund has been processed successfully. Here are the details:</p>
        
        <div class="details">
            <h3 style="margin-top: 0;">Refund Details</h3>
            <div class="details-row">
                <span class="label">Product</span>
                <span class="value">{{ $product->name ?? 'Subscription' }}</span>
            </div>
            <div class="details-row">
                <span class="label">Refund Amount</span>
                <span class="value">{{ $currency }} {{ $refundAmount }}</span>
            </div>
            @if($refundId)
            <div class="details-row">
                <span class="label">Reference ID</span>
                <span class="value">{{ $refundId }}</span>
            </div>
            @endif
        </div>
        
        <p>The refund will be credited to your original payment method within 5-10 business days, depending on your bank.</p>
        
        <p>We're sorry to see you go. If you have any questions or if there's anything we can do to improve, please don't hesitate to reach out.</p>
    </div>
    
    <div class="footer">
        <p>Thank you for being a customer.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>
