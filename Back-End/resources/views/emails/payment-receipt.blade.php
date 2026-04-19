<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Receipt</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #10B981; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .receipt-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .receipt-table th, .receipt-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .receipt-table th { background: #f8f9fa; font-weight: bold; }
        .total-row { background: #f0f9ff; font-weight: bold; }
        .button { 
            display: inline-block; 
            padding: 14px 32px; 
            background: #4F46E5; 
            color: white !important; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: bold; 
            margin: 20px 0; 
            font-size: 16px;
        }
        .success-badge { 
            background: #10B981; 
            color: white; 
            padding: 8px 16px; 
            border-radius: 20px; 
            font-size: 14px; 
            font-weight: bold;
            display: inline-block;
            margin: 10px 0;
        }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Payment Confirmation</h1>
            <div class="success-badge">✅ Payment Successful</div>
        </div>
        
        <div class="content">
            <h2>Dear {{ $student_name }},</h2>
            
            <p>Thank you for your payment! Your transaction has been processed successfully.</p>
            
            <table class="receipt-table">
                <tr>
                    <th>Course</th>
                    <td>{{ $course_name }}</td>
                </tr>
                <tr>
                    <th>Amount Paid</th>
                    <td><strong>${{ number_format($amount, 2) }}</strong></td>
                </tr>
                <tr>
                    <th>Transaction ID</th>
                    <td>{{ $transaction_id }}</td>
                </tr>
                <tr>
                    <th>Payment Date</th>
                    <td>{{ $payment_date }}</td>
                </tr>
                <tr>
                    <th>Payment Method</th>
                    <td>Chapa Payment Gateway</td>
                </tr>
                <tr class="total-row">
                    <th>Status</th>
                    <td>✅ <strong>PAID</strong></td>
                </tr>
            </table>
            
            <div style="background: #f0f9ff; border-left: 4px solid #3B82F6; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <strong>🎉 What's Next?</strong><br>
                Your course access has been activated! You can now access all course materials, lessons, and tutorials.
            </div>
            
            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}/student" class="button">
                    Access Your Dashboard
                </a>
            </div>
            
            <p><strong>Need Help?</strong><br>
            If you have any questions about your payment or course access, please contact our support team.</p>
            
            <p>Happy Learning!<br>
            <strong>The Academic Tutorial System Team</strong></p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Academic Tutorial System. All rights reserved.</p>
            <p>
                <a href="{{ config('app.url') }}/support">Contact Support</a> • 
                <a href="{{ config('app.url') }}/refund-policy">Refund Policy</a>
            </p>
        </div>
    </div>
</body>
</html>