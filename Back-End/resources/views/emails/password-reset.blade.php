<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Your Password</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #DC2626; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .button { 
            display: inline-block; 
            padding: 14px 32px; 
            background: #DC2626; 
            color: white !important; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: bold; 
            margin: 20px 0; 
            font-size: 16px;
        }
        .warning { 
            background: #fef3c7; 
            border-left: 4px solid #f59e0b; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 4px;
        }
        .security-notice { 
            background: #fee2e2; 
            border-left: 4px solid #dc2626; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 4px;
        }
        .fallback-link { 
            word-break: break-all; 
            background: #f8f9fa; 
            padding: 12px; 
            border: 1px solid #dee2e6; 
            border-radius: 4px; 
            font-family: monospace;
        }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Password Reset Request</h1>
        </div>
        
        <div class="content">
            <h2>Hello {{ $user->name }},</h2>
            
            <p>We received a request to reset your password for your Academic Tutorial System account.</p>
            
            <p>To reset your password, click the button below:</p>
            
            <div style="text-align: center;">
                <a href="{{ $resetUrl }}" class="button">
                    Reset My Password
                </a>
            </div>
            
            <div class="warning">
                <strong>⏰ Important:</strong> This password reset link will expire in 60 minutes for security reasons.
            </div>
            
            <p>If the button doesn't work, copy and paste the link below into your browser:</p>
            
            <p class="fallback-link">
                {{ $resetUrl }}
            </p>
            
            <div class="security-notice">
                <strong>🛡️ Security Notice:</strong><br>
                • If you didn't request this password reset, please ignore this email<br>
                • Your password will remain unchanged<br>
                • Consider changing your password if you suspect unauthorized access<br>
                • Never share your password with anyone
            </div>
            
            <p><strong>Need Help?</strong><br>
            If you're having trouble resetting your password, please contact our support team.</p>
            
            <p>Best regards,<br>
            The Academic Tutorial System Team</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} Academic Tutorial System. All rights reserved.</p>
            <p>
                <a href="{{ config('app.url') }}/privacy">Privacy Policy</a> • 
                <a href="{{ config('app.url') }}/terms">Terms of Service</a> • 
                <a href="mailto:support@tutorialsystem.com">Support</a>
            </p>
        </div>
    </div>
</body>
</html>