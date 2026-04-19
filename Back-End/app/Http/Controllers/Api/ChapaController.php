<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentReceiptEmail;

class ChapaController extends Controller
{
    /**
     * Initialize Chapa payment and redirect user to checkout
     */
    public function initialize(Request $request)
    {
        try {
            $user = Auth::user();
            $student = Student::where('user_id', $user->id)->first();

            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Student record not found.'], 404);
            }

            if (!$student->selected_course_id) {
                return response()->json(['success' => false, 'message' => 'Please select a course before making payment.'], 400);
            }

            $amount = (float) $student->final_price;
            if ($amount <= 0) {
                return response()->json(['success' => false, 'message' => 'Price not calculated correctly.'], 400);
            }

            // Generate unique reference
            $tx_ref = 'TUTORIAL-' . now()->timestamp . '-' . $user->id . '-' . Str::random(6);

            $payload = [
                'amount' => $amount,
                'currency' => 'ETB',
                'email' => $user->email,
                'first_name' => $user->name,
                'tx_ref' => $tx_ref,
                'callback_url' => route('payment.verify', ['tx_ref' => $tx_ref]),
                'return_url' => "http://localhost:5173/dashboard?payment=success&tx_ref=" . $tx_ref,
                'customization' => [
                    'title' => 'Tutorial Payment',
                    // FIX: No colons or special characters except hyphens, underscores, or dots
                    'description' => 'Payment for Course ID ' . $student->selected_course_id 
                ]
            ];

            // Initialize Chapa payment
            $response = Http::withoutVerifying()
                ->withToken(env('CHAPA_SECRET_KEY'))
                ->post('https://api.chapa.co/v1/transaction/initialize', $payload);

            if (!$response->successful()) {
                Log::error('Chapa API Error', ['body' => $response->body()]);
                return response()->json(['success' => false, 'message' => 'Failed to initialize payment gateway'], 500);
            }

            $result = $response->json();

            // Create payment record matching your database column 'transaction_reference'
            $payment = Payment::create([
                'user_id' => $user->id,
                'course_id' => $student->selected_course_id,
                'description' => 'Course ID: ' . $student->selected_course_id,
                'transaction_reference' => $tx_ref, // Matches your DB column
                'amount' => $amount,
                'currency' => 'ETB',
                'status' => 'pending',
                'payment_method' => 'Chapa',
                'checkout_url' => $result['data']['checkout_url'],
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $result['data']['checkout_url'],
                'tx_ref' => $tx_ref
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Initialization Exception: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify payment and unlock dashboard
     */
    public function verify($tx_ref)
    {
        try {
            Log::info("Verifying transaction: " . $tx_ref);

            // Try to verify with Chapa with better error handling
            $response = Http::withoutVerifying()
                ->withToken(env('CHAPA_SECRET_KEY'))
                ->timeout(30) // Longer timeout
                ->retry(3, 1000) // Retry 3 times with 1 second delay
                ->get("https://api.chapa.co/v1/transaction/verify/" . $tx_ref);

            $data = $response->json();
            Log::info("Chapa verification response", ['tx_ref' => $tx_ref, 'response' => $data]);

            if ($response->successful() && $data['status'] == 'success') {
                
                // 1. Find record using 'transaction_reference' column
                $payment = Payment::where('transaction_reference', $tx_ref)->first();
                
                if ($payment) {
                    // Check if payment is actually successful on Chapa
                    if (isset($data['data']['status']) && $data['data']['status'] == 'success') {
                        // 2. Mark payment as completed
                        $payment->update(['status' => 'completed']);
                        Log::info("Payment marked as completed", ['payment_id' => $payment->id]);

                        // 3. Find student and update is_paid status
                        $student = Student::where('user_id', $payment->user_id)->first();
                        
                        if ($student) {
                            $student->update(['is_paid' => true]);
                            Log::info("Student marked as paid", ['user_id' => $payment->user_id]);

                            // 4. Create Enrollment record
                            if ($student->selected_course_id) {
                                $enrollment = Enrollment::firstOrCreate([
                                    'user_id' => $payment->user_id,
                                    'tutorial_id' => $student->selected_course_id,
                                ], [
                                    'course_id' => $student->selected_course_id,
                                    'status' => 'active',
                                    'enrolled_at' => now(),
                                ]);
                                Log::info("Enrollment created/found for User {$payment->user_id}");
                                
                                // 5. Send Payment Receipt Email
                                try {
                                    $course = Course::find($student->selected_course_id);
                                    if ($course) {
                                        Mail::to($student->user->email)->send(new PaymentReceiptEmail($payment, $student, $course));
                                        Log::info("Payment receipt email sent to: " . $student->user->email);
                                    } else {
                                        Log::warning("Course not found for payment receipt email", ['course_id' => $student->selected_course_id]);
                                    }
                                } catch (\Exception $e) {
                                    Log::error("Failed to send payment receipt email: " . $e->getMessage());
                                    // Don't fail the payment verification if email fails
                                }
                            }
                        }
                        
                        return response()->json(['success' => true, 'message' => 'Payment verified successfully']);
                    } else {
                        Log::warning("Payment not successful on Chapa", ['tx_ref' => $tx_ref, 'chapa_status' => $data['data']['status'] ?? 'unknown']);
                        return response()->json(['success' => false, 'message' => 'Payment not completed on Chapa'], 400);
                    }
                } else {
                    Log::error("Payment record not found", ['tx_ref' => $tx_ref]);
                    return response()->json(['success' => false, 'message' => 'Payment record not found'], 404);
                }
            }

            Log::warning("Chapa verification failed", ['tx_ref' => $tx_ref, 'response' => $data]);
            return response()->json(['success' => false, 'message' => 'Payment verification failed'], 400);

        } catch (\Exception $e) {
            Log::error('Verification Error: ' . $e->getMessage(), ['tx_ref' => $tx_ref]);
            
            // If it's a network error, we might want to retry later
            if (strpos($e->getMessage(), 'cURL') !== false || strpos($e->getMessage(), 'timeout') !== false) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Network error during verification. Please try again.',
                    'retry' => true
                ], 503);
            }
            
            return response()->json(['success' => false, 'message' => 'Server error during verification'], 500);
        }
    }

    /**
     * Auto-verify any pending payments for the current user
     */
    public function autoVerify()
    {
        try {
            $user = Auth::user();
            
            // Find all pending payments for this user from the last 24 hours
            $pendingPayments = Payment::where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subHours(24))
                ->get();
            
            $verifiedCount = 0;
            $lastVerifiedPayment = null;
            
            foreach ($pendingPayments as $payment) {
                Log::info("Auto-verifying payment: " . $payment->transaction_reference);
                
                try {
                    // Try to verify with Chapa
                    $response = Http::withoutVerifying()
                        ->withToken(env('CHAPA_SECRET_KEY'))
                        ->timeout(15) // Shorter timeout for auto-verify
                        ->get("https://api.chapa.co/v1/transaction/verify/" . $payment->transaction_reference);

                    if ($response->successful()) {
                        $data = $response->json();
                        
                        if (isset($data['status']) && $data['status'] == 'success' && 
                            isset($data['data']['status']) && $data['data']['status'] == 'success') {
                            
                            // Update payment status
                            $payment->update(['status' => 'completed']);
                            Log::info("Auto-verified payment: " . $payment->transaction_reference);
                            
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
                                    
                                    // Send Payment Receipt Email
                                    try {
                                        $course = Course::find($student->selected_course_id);
                                        if ($course) {
                                            Mail::to($student->user->email)->send(new PaymentReceiptEmail($payment, $student, $course));
                                            Log::info("Auto-verify payment receipt email sent to: " . $student->user->email);
                                        }
                                    } catch (\Exception $e) {
                                        Log::error("Failed to send auto-verify payment receipt email: " . $e->getMessage());
                                    }
                                }
                            }
                            
                            $verifiedCount++;
                            $lastVerifiedPayment = $payment;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Auto-verify failed for payment " . $payment->transaction_reference . ": " . $e->getMessage());
                    // Continue with other payments
                }
            }
            
            return response()->json([
                'success' => true,
                'verified_count' => $verifiedCount,
                'message' => $verifiedCount > 0 ? "Verified {$verifiedCount} payment(s)" : "No pending payments to verify"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Auto-verify error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Auto-verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status for logged-in user
     */
    public function status()
    {
        try {
            $user = Auth::user();
            $student = $user->student;
            $payment = Payment::where('user_id', $user->id)->latest()->first();

            return response()->json([
                'success' => true,
                'status' => $payment?->status ?? 'none',
                'is_paid' => $student?->is_paid ?? false,
                'amount_due' => $student?->final_price ?? 0,
                'currency' => 'ETB',
                'selected_course' => $student && $student->selected_course_id ? [
                    'id' => $student->selected_course_id,
                    'title' => $student->course?->title ?? 'Unknown Course'
                ] : null,
                'payment_reference' => $payment?->transaction_reference,
                'requires_course_selection' => !$student?->selected_course_id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}