<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyPendingPayments extends Command
{
    protected $signature = 'payments:verify-pending';
    protected $description = 'Automatically verify pending payments with Chapa';

    public function handle()
    {
        $this->info('Starting automatic payment verification...');
        
        // Get pending payments from the last 24 hours
        $pendingPayments = Payment::where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();
        
        $this->info("Found {$pendingPayments->count()} pending payments to verify");
        
        $verified = 0;
        $failed = 0;
        
        foreach ($pendingPayments as $payment) {
            $this->info("Verifying payment: {$payment->transaction_reference}");
            
            try {
                $response = Http::withoutVerifying()
                    ->withToken(env('CHAPA_SECRET_KEY'))
                    ->timeout(30)
                    ->retry(3, 1000)
                    ->get("https://api.chapa.co/v1/transaction/verify/" . $payment->transaction_reference);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['status']) && $data['status'] == 'success' && 
                        isset($data['data']['status']) && $data['data']['status'] == 'success') {
                        
                        // Update payment status
                        $payment->update(['status' => 'completed']);
                        
                        // Update student status
                        $student = Student::where('user_id', $payment->user_id)->first();
                        if ($student) {
                            $student->update(['is_paid' => true]);
                            
                            // Create enrollment
                            if ($student->selected_course_id) {
                                Enrollment::firstOrCreate([
                                    'user_id' => $payment->user_id,
                                    'tutorial_id' => $student->selected_course_id,
                                ], [
                                    'course_id' => $student->selected_course_id,
                                    'status' => 'active',
                                    'enrolled_at' => now(),
                                ]);
                            }
                        }
                        
                        $this->info("✅ Payment verified: {$payment->transaction_reference}");
                        $verified++;
                        
                        Log::info("Automatic payment verification successful", [
                            'payment_id' => $payment->id,
                            'tx_ref' => $payment->transaction_reference,
                            'user_id' => $payment->user_id
                        ]);
                    } else {
                        $this->warn("❌ Payment not successful on Chapa: {$payment->transaction_reference}");
                        $failed++;
                    }
                } else {
                    $this->error("❌ Chapa API error for: {$payment->transaction_reference}");
                    $failed++;
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Verification error for {$payment->transaction_reference}: " . $e->getMessage());
                $failed++;
            }
        }
        
        $this->info("Verification complete: {$verified} verified, {$failed} failed");
        
        return 0;
    }
}