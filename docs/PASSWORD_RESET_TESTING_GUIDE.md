# Password Reset Testing Guide

## 🚀 Quick Fix for 404 Error

The 404 error occurs because the Laravel server is not running. Follow these steps:

### 1. Start the Laravel Server

```bash
cd Back-End
php artisan serve --host=127.0.0.1 --port=8000
```

**OR** if you want to access from other devices:

```bash
cd Back-End
php artisan serve --host=192.168.1.5 --port=8000
```

### 2. Verify Server is Running

Open your browser and go to:
- http://127.0.0.1:8000/api/health

You should see:
```json
{
  "status": "ok",
  "timestamp": "2026-01-31T...",
  "environment": "local"
}
```

### 3. Test Password Reset

1. **Go to Login Page**: http://localhost:5173/login
2. **Click "Forgot your password?"**
3. **Enter your email address**
4. **Click "Send Reset Email"**
5. **Check your email** for the reset link
6. **Click the reset link** in the email
7. **Enter new password** and confirm
8. **Try logging in** with the new password

## 🧪 Manual API Testing

If you want to test the API directly:

### Test Forgot Password Endpoint

```bash
curl -X POST http://127.0.0.1:8000/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"your-email@example.com"}'
```

Expected response:
```json
{
  "success": true,
  "message": "Password reset email sent! Please check your inbox..."
}
```

### Test with Invalid Email

```bash
curl -X POST http://127.0.0.1:8000/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"nonexistent@example.com"}'
```

Expected response:
```json
{
  "success": false,
  "message": "Please enter a valid email address that exists in our system."
}
```

## 🔧 Troubleshooting

### If you still get 404 errors:

1. **Clear Laravel cache**:
   ```bash
   cd Back-End
   php artisan route:clear
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Check route registration**:
   ```bash
   cd Back-End
   php artisan route:list | grep forgot
   ```

3. **Verify the route exists**:
   You should see:
   ```
   POST  api/auth/forgot-password  App\Http\Controllers\Auth\PasswordResetController@sendResetEmail
   ```

### If emails are not being sent:

1. **Check .env configuration**:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp-relay.brevo.com
   MAIL_PORT=587
   MAIL_USERNAME=a13ef6001@smtp-brevo.com
   MAIL_PASSWORD=your-brevo-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=workuadane06@gmail.com
   MAIL_FROM_NAME="Academic Tutorial System"
   ```

2. **Test email configuration**:
   ```bash
   php test_email_system.php
   ```

## ✅ Expected Behavior

1. **Login Page**: Shows "Forgot your password?" link
2. **Forgot Password Mode**: Allows email input and sends reset email
3. **Email Received**: Professional email with reset link
4. **Reset Page**: Secure form for new password
5. **Success**: Redirects to login with success message
6. **Login Works**: Can login with new password

## 🎯 Status

The password reset functionality is **fully implemented** and should work once the server is running. The 404 error is simply because the Laravel server needs to be started.

**Start the server and try again!**