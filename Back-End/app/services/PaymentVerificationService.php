<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Student;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentVerificationService
{
    /**
     * Automatically verify a payment
     */
    public static function verifyPayment($tx_ref)
    {
        try {
            Log::info("Auto-verifying payment: " . $tx_ref);
            
            // Find the payment record
            $payment = Payment::where('transaction_reference', $tx_ref)->first();
            if (!$payment) {
                Log::error("Payment not found for tx_ref: " . $tx_ref);
                return false;
            }
            
            // If already completed, skip
            if ($payment->status === 'completed') {
                Log::info("Payment already completed: " . $tx_ref);
                return true;
            }
            
            // Try to verify with Chapa
            $response = Http::withoutVerifying()
                ->withToken(env('CHAPA_SECRET_KEY'))
                ->timeout(30)
                ->retry(3, 2000) // 3 retries with 2 second delay
                ->get("https://api.chapa.co/v1/transaction/verify/" . $tx_ref);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] == 'success' && 
                    isset($data['data']['status']) && $data['data']['status'] == 'success') {
                    
                    // Update payment status
                    $payment->update(['status' => 'completed']);
                    Log::info("Payment marked as completed: " . $tx_ref);
                    
                    // Update student status
                    $student = Student::where('user_id', $payment->user_id)->first();
                    if ($student) {
                        $student->update(['is_paid' => true]);
                        Log::info("Student marked as paid: " . $payment->user_id);
                        
                        // Create enrollment
                        if ($student->selected_course_id) {
                            $enrollment = Enrollment::firstOrCreate([
                                'user_id' => $payment->user_id,
                                'tutorial_id' => $student->selected_course_id,
                            ], [
                                'course_id' => $student->selected_course_id,
                                'status' => 'active',
                                'enrolled_at' => now(),
                            ]);
                            
                            Log::info("Enrollment created for user: " . $payment->user_id);
                        }
                    }
                    
                    return true;
                } else {
                    Log::warning("Payment not successful on Chapa: " . $tx_ref, ['chapa_data' => $data]);
                    return false;
                }
            } else {
                Log::error("Chapa API error for: " . $tx_ref, ['status' => $response->status()]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error("Payment verification error for " . $tx_ref . ": " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify all pending payments for a user
     */
    public static function verifyUserPayments($userId)
    {
        $pendingPayments = Payment::where('user_id', $userId)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();
        
        $verified = 0;
        foreach ($pendingPayments as $payment) {
            if (self::verifyPayment($payment->transaction_reference)) {
                $verified++;
            }
        }
        
        return $verified;
    }
}