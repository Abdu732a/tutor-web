# Testing the Payment Flow

## Quick Start Guide

### 1. Database Setup
```bash
cd Back-End
php artisan migrate
```

### 2. Environment Configuration
Add to your `.env` file:
```env
CHAPA_SECRET_KEY=your_chapa_secret_key
CHAPA_PUBLIC_KEY=your_chapa_public_key
```

### 3. Start the Servers
```bash
# Backend (Laravel)
cd Back-End
php artisan serve

# Frontend (React)
cd Front-End
npm run dev
```

### 4. Test the Payment Flow

#### Option A: Use the Test Component
1. Navigate to `http://localhost:5173/test-payment`
2. Click "Test Status" to check payment status
3. Click "Select Course" to choose a course
4. Click "Test Payment" to initialize payment
5. Review the test results

#### Option B: Manual Testing
1. Register as a student with preferences
2. Login and go to dashboard (`/student`)
3. Select a course from the guest dashboard
4. Click "Enroll & Pay" button
5. Complete payment on Chapa (use test cards)
6. Verify redirect back to dashboard
7. Confirm full student dashboard access

## Test Data

### Chapa Test Cards
- **Card Number**: 4000000000000002
- **Expiry**: Any future date (e.g., 12/25)
- **CVV**: Any 3 digits (e.g., 123)
- **OTP**: 123456

### Sample Student Preferences
```json
{
  "course_type": "programming",
  "learning_preference": "Individual",
  "learning_mode": "Online",
  "preferred_days": ["Monday", "Wednesday", "Friday"],
  "hours_per_day": 2
}
```

## API Endpoints to Test

### 1. Get Payment Status
```bash
GET /api/student/payment-status
Authorization: Bearer {token}
```

### 2. Select Course
```bash
POST /api/payment/select-course
Authorization: Bearer {token}
Content-Type: application/json

{
  "course_id": 1
}
```

### 3. Initialize Payment
```bash
POST /api/payment/initialize
Authorization: Bearer {token}
Content-Type: application/json

{
  "course_id": 1
}
```

### 4. Verify Payment
```bash
GET /api/payment/verify/{tx_ref}
Authorization: Bearer {token}
```

## Expected Flow

1. **Student Registration** → User completes registration
2. **Login** → User logs in and sees guest dashboard
3. **Course Selection** → System shows courses based on preferences
4. **Price Calculation** → Backend calculates total price
5. **Payment Initialization** → Creates payment record and Chapa URL
6. **Payment Processing** → User completes payment on Chapa
7. **Payment Verification** → System verifies and updates status
8. **Dashboard Unlock** → User gains full dashboard access

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure MySQL is running
   - Check database credentials in `.env`
   - Run `php artisan migrate`

2. **Chapa API Error**
   - Verify API keys in `.env`
   - Check network connectivity
   - Ensure using correct Chapa environment (test/live)

3. **Course Not Found**
   - Ensure courses exist in database
   - Check course is marked as active
   - Verify course category matches student preferences

4. **Payment Verification Failed**
   - Check Chapa webhook configuration
   - Verify transaction reference format
   - Check payment record in database

### Debug Information

Enable debug mode in Laravel:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

Check logs:
```bash
tail -f Back-End/storage/logs/laravel.log
```

## Success Indicators

✅ **Guest Dashboard**: Shows course selection and pricing
✅ **Course Selection**: Updates price when course is selected
✅ **Payment Initialization**: Returns Chapa checkout URL
✅ **Payment Verification**: Updates student status to paid
✅ **Dashboard Transition**: Shows full student dashboard after payment

## Production Considerations

- Remove test routes and components
- Use production Chapa API keys
- Enable HTTPS for payment security
- Set up proper error monitoring
- Configure payment webhooks
- Test with real payment methods

## Support

If you encounter issues:
1. Check the browser console for errors
2. Review Laravel logs for backend errors
3. Verify database records are created correctly
4. Test API endpoints individually
5. Use the test component for debugging