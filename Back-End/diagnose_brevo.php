<?php
/**
 * Brevo SMTP Diagnostic Script
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Brevo SMTP Diagnostic Report\n";
echo "================================\n\n";

// Check current configuration
echo "📋 Current Configuration:\n";
echo "MAIL_MAILER: " . env('MAIL_MAILER') . "\n";
echo "MAIL_HOST: " . env('MAIL_HOST') . "\n";
echo "MAIL_PORT: " . env('MAIL_PORT') . "\n";
echo "MAIL_USERNAME: " . env('MAIL_USERNAME') . "\n";
echo "MAIL_PASSWORD: " . (env('MAIL_PASSWORD') ? 'SET (' . strlen(env('MAIL_PASSWORD')) . ' chars)' : 'NOT SET') . "\n";
echo "MAIL_ENCRYPTION: " . env('MAIL_ENCRYPTION') . "\n";
echo "MAIL_FROM_ADDRESS: " . env('MAIL_FROM_ADDRESS') . "\n\n";

// Test SMTP connection manually
echo "🔌 Testing SMTP Connection...\n";

try {
    $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
        env('MAIL_HOST'),
        env('MAIL_PORT'),
        env('MAIL_ENCRYPTION') === 'tls'
    );
    
    $transport->setUsername(env('MAIL_USERNAME'));
    $transport->setPassword(env('MAIL_PASSWORD'));
    
    // Try to start the transport
    $transport->start();
    echo "✅ SMTP connection successful!\n";
    $transport->stop();
    
} catch (Exception $e) {
    echo "❌ SMTP connection failed: " . $e->getMessage() . "\n";
    
    // Additional diagnostics
    echo "\n🔧 Diagnostic Information:\n";
    echo "Error Type: " . get_class($e) . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    
    // Check if it's an authentication issue
    if (strpos($e->getMessage(), '535') !== false) {
        echo "\n💡 Authentication Issue Detected:\n";
        echo "This is a 535 authentication error. Possible causes:\n";
        echo "1. SMTP key is incorrect or expired\n";
        echo "2. Brevo account needs additional verification\n";
        echo "3. Username format might be wrong\n";
        echo "4. Account might be suspended or limited\n\n";
        
        echo "🔍 Troubleshooting Steps:\n";
        echo "1. Check Brevo dashboard for account status\n";
        echo "2. Verify the SMTP key is active\n";
        echo "3. Check if account verification is complete\n";
        echo "4. Try generating another new SMTP key\n";
    }
}

// Check internet connectivity
echo "\n🌐 Testing Internet Connectivity...\n";
try {
    $context = stream_context_create([
        "http" => [
            "timeout" => 10
        ]
    ]);
    
    $response = file_get_contents("https://api.brevo.com", false, $context);
    echo "✅ Internet connection to Brevo API working\n";
} catch (Exception $e) {
    echo "❌ Internet connectivity issue: " . $e->getMessage() . "\n";
}

// Check DNS resolution
echo "\n🔍 Testing DNS Resolution...\n";
$ip = gethostbyname('smtp-relay.brevo.com');
if ($ip !== 'smtp-relay.brevo.com') {
    echo "✅ DNS resolution working: smtp-relay.brevo.com -> $ip\n";
} else {
    echo "❌ DNS resolution failed for smtp-relay.brevo.com\n";
}

echo "\n📊 Summary:\n";
echo "If authentication is failing consistently, the issue is likely:\n";
echo "1. Brevo account needs additional verification\n";
echo "2. SMTP key permissions or format issue\n";
echo "3. Account limitations or restrictions\n\n";

echo "🎯 Recommended Next Steps:\n";
echo "1. Check Brevo dashboard for any alerts or verification requirements\n";
echo "2. Try using the Brevo API instead of SMTP\n";
echo "3. Contact Brevo support if account verification is needed\n";
echo "4. Consider switching to SendGrid as backup plan\n";

?>