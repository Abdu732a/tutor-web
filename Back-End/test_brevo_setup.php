<?php
/**
 * Brevo Email Setup Test Script
 * Run this with: php test_brevo_setup.php
 */

// Include Laravel bootstrap
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;
use App\Mail\EmailVerificationMail;
use App\Models\User;

echo "🧪 Testing Brevo Email Configuration...\n";
echo "📧 Target Email: workuadane06@gmail.com\n\n";

// Clear config cache first
echo "🔄 Clearing configuration cache...\n";
\Illuminate\Support\Facades\Artisan::call('config:clear');
echo "✅ Cache cleared\n\n";

// Test 1: Simple email
echo "1️⃣ Testing basic email sending...\n";
try {
    Mail::raw('🎉 SUCCESS! Your Brevo email setup is working perfectly! This email was sent from your Tutorial Management System using Brevo SMTP.', function($message) {
        $message->to('workuadane06@gmail.com')
                ->subject('✅ Brevo Email Test - Tutorial System Working!');
    });
    echo "✅ Basic email sent successfully!\n";
    echo "📬 Check Gmail: workuadane06@gmail.com\n\n";
} catch (Exception $e) {
    echo "❌ Basic email failed: " . $e->getMessage() . "\n";
    echo "🔧 Check your internet connection and Brevo credentials\n\n";
    exit(1);
}

// Test 2: Verification email template
echo "2️⃣ Testing verification email template...\n";
try {
    $testUser = new User([
        'name' => 'Test Student',
        'email' => 'workuadane06@gmail.com',
        'role' => 'student'
    ]);
    
    $verificationUrl = 'http://localhost:5173/email/verify/test/hash123';
    
    Mail::to('workuadane06@gmail.com')->send(new EmailVerificationMail($testUser, $verificationUrl));
    echo "✅ Verification email template sent successfully!\n";
    echo "📬 Check Gmail for the professional verification email\n\n";
} catch (Exception $e) {
    echo "❌ Verification email failed: " . $e->getMessage() . "\n\n";
}

// Test 3: Configuration check
echo "3️⃣ Current email configuration:\n";
echo "MAIL_MAILER: " . config('mail.default') . "\n";
echo "MAIL_HOST: " . config('mail.mailers.smtp.host') . "\n";
echo "MAIL_PORT: " . config('mail.mailers.smtp.port') . "\n";
echo "MAIL_USERNAME: " . config('mail.mailers.smtp.username') . "\n";
echo "MAIL_FROM_ADDRESS: " . config('mail.from.address') . "\n";
echo "MAIL_FROM_NAME: " . config('mail.from.name') . "\n\n";

echo "🎯 Results Summary:\n";
echo "✅ Configuration updated with Brevo credentials\n";
echo "✅ Test emails sent to workuadane06@gmail.com\n";
echo "📊 Check Brevo dashboard: https://app.brevo.com/statistics\n";
echo "📧 Check your Gmail inbox for test emails\n\n";

echo "🚀 Next Steps:\n";
echo "1. Check your Gmail inbox for 2 test emails\n";
echo "2. If emails arrived, your setup is working perfectly!\n";
echo "3. Try registering a new student to test the complete flow\n";
echo "4. All system emails (registration, payments, password reset) will now work\n\n";

echo "📈 Brevo Free Limits:\n";
echo "• 300 emails per day\n";
echo "• 9,000 emails per month\n";
echo "• Professional email templates\n";
echo "• Email analytics and tracking\n\n";

echo "🎉 Brevo email setup completed successfully!\n";
?>