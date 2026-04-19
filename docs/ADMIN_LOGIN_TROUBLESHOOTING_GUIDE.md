# Admin Login Troubleshooting Guide 🔧

## Current Status ✅
- **Database**: Admin user exists and is properly configured
- **API**: Login endpoint is working correctly (tested successfully)
- **Credentials**: `admin@example.com` / `admin123` are valid

## Verified Working Credentials
```
Email: admin@example.com
Password: admin123
```

## Step-by-Step Troubleshooting

### 1. **Test Backend Connection First**
Open the file `test_frontend_backend_connection.html` in your browser and click "Test Connection" to verify:
- ✅ Backend server is running
- ✅ API endpoints are accessible
- ✅ No CORS issues

### 2. **Check Backend Server Status**
Make sure your Laravel backend is running:
```bash
cd Back-End
php artisan serve
```
You should see: `Laravel development server started: http://127.0.0.1:8000`

### 3. **Check Frontend Development Server**
Make sure your React frontend is running:
```bash
cd Front-End
npm run dev
```
You should see the Vite dev server running on `http://localhost:5173`

### 4. **Test Direct API Login**
Open browser console and run:
```javascript
fetch('http://127.0.0.1:8000/api/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    email: 'admin@example.com',
    password: 'admin123'
  })
})
.then(response => response.json())
.then(data => console.log('Login result:', data))
.catch(error => console.error('Login error:', error));
```

### 5. **Common Issues and Solutions**

#### Issue: "Network Error" or "Cannot connect to server"
**Solution**: Backend server is not running
```bash
cd Back-End
php artisan serve
```

#### Issue: "CORS Error" in browser console
**Solution**: Check if both servers are running on correct ports:
- Backend: `http://127.0.0.1:8000`
- Frontend: `http://localhost:5173`

#### Issue: "Invalid credentials" with correct password
**Solution**: Run the password reset script:
```bash
php debug_admin_login.php
```

#### Issue: Login succeeds but redirects to wrong page
**Solution**: Check the role-based redirect in `Login.tsx`:
- Admin should redirect to `/admin`
- Make sure the user role is 'admin' in the response

#### Issue: "Token not found" or authentication errors
**Solution**: Check localStorage in browser dev tools:
- Should have `token` key with JWT token
- Should have `user` key with user data

### 6. **Browser Developer Tools Debugging**

#### Check Network Tab:
1. Open browser dev tools (F12)
2. Go to Network tab
3. Try to login
4. Look for the `/api/login` request
5. Check if it returns 200 OK or shows an error

#### Check Console Tab:
1. Look for any JavaScript errors
2. Check for CORS errors
3. Look for network connection errors

#### Check Application/Storage Tab:
1. Check localStorage for `token` and `user` keys
2. Clear localStorage if needed: `localStorage.clear()`

### 7. **Manual Database Verification**
If login still fails, verify the admin user manually:
```bash
php debug_admin_login.php
```

This will:
- ✅ Show all admin users
- ✅ Test password verification
- ✅ Fix any issues automatically
- ✅ Reset password if needed

### 8. **Frontend Environment Check**
Verify the frontend `.env` file:
```
VITE_API_BASE_URL=http://127.0.0.1:8000
```

### 9. **Test with Different Browser**
Try logging in with:
- Different browser (Chrome, Firefox, Edge)
- Incognito/Private mode
- Clear browser cache and cookies

### 10. **Check Laravel Logs**
If login fails, check Laravel logs:
```bash
tail -f Back-End/storage/logs/laravel.log
```

## Quick Test Commands

### Test API directly:
```bash
node test_admin_login_api.js
```

### Test database:
```bash
php debug_admin_login.php
```

### Test frontend connection:
Open `test_frontend_backend_connection.html` in browser

## Expected Successful Login Flow

1. **Frontend sends POST to `/api/login`**
2. **Backend validates credentials**
3. **Backend returns success response with token**
4. **Frontend stores token in localStorage**
5. **Frontend redirects to `/admin` dashboard**

## If All Else Fails

1. **Restart both servers**:
   ```bash
   # Terminal 1 - Backend
   cd Back-End
   php artisan serve
   
   # Terminal 2 - Frontend  
   cd Front-End
   npm run dev
   ```

2. **Clear browser data**:
   - Clear localStorage: `localStorage.clear()`
   - Clear cookies and cache
   - Try incognito mode

3. **Reset admin user**:
   ```bash
   php debug_admin_login.php
   ```

## Contact Information
If you're still having issues, please provide:
1. **Exact error message** you see
2. **Browser console errors** (F12 → Console tab)
3. **Network tab results** (F12 → Network tab)
4. **Which step fails** in the troubleshooting guide

The login system is verified to be working correctly, so the issue is likely environmental (servers not running, network issues, or browser cache).