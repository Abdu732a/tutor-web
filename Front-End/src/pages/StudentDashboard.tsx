import { useState, useEffect } from "react";
import { apiClient } from "@/lib/api";
import PStudent from "./PStudent";
import { Button } from "@/components/ui/button";
import { Loader2, CreditCard, ShieldCheck, RefreshCw } from "lucide-react";
import GuestDashboard from "./GuestDashboard";

export default function StudentDashboard() {
  const [loading, setLoading] = useState(true);
  const [isPaid, setIsPaid] = useState(false);
  const [isRedirecting, setIsRedirecting] = useState(false);
  const [paymentInfo, setPaymentInfo] = useState<any>(null);
  const [dashboardData, setDashboardData] = useState<any>(null);
  const [selectedCourseId, setSelectedCourseId] = useState<string | null>(null);

  /**
   * Auto-verify any pending payments and check current status
   */
  const checkStatusWithAutoVerify = async () => {
    try {
      // First, try to auto-verify any pending payments
      try {
        const autoVerifyRes = await apiClient.get("/payment/auto-verify");
        if (autoVerifyRes.data.verified_count > 0) {
          console.log(`Auto-verified ${autoVerifyRes.data.verified_count} payment(s)`);
        }
      } catch (autoVerifyError) {
        console.warn("Auto-verify failed, continuing with status check:", autoVerifyError);
      }

      // Then check the current payment status
      const payRes = await apiClient.get("/student/payment-status");
      const paidStatus = !!payRes.data.is_paid;

      setIsPaid(paidStatus);
      setPaymentInfo(payRes.data);

      // Set selected course if available
      if (payRes.data.selected_course) {
        setSelectedCourseId(payRes.data.selected_course.id.toString());
      }

      if (paidStatus) {
        // Fetch the tutorials/enrollments for the paid dashboard
        const dashRes = await apiClient.get("/student/dashboard");
        setDashboardData(dashRes.data.dashboard);
      }
    } catch (error) {
      console.error("Error loading dashboard data:", error);
    } finally {
      setLoading(false);
    }
  };

  /**
   * Handle the return from Chapa payment
   * Automatically triggers verification if tx_ref exists in the URL
   */
  useEffect(() => {
    const handleReturnAndVerify = async () => {
      const urlParams = new URLSearchParams(window.location.search);
      const tx_ref = urlParams.get('tx_ref');

      if (tx_ref) {
        setLoading(true);
        try {
          console.log("Verifying payment with tx_ref:", tx_ref);

          // Trigger the verify method in your Backend ChapaController
          const verifyRes = await apiClient.get(`/payment/verify/${tx_ref}`);

          if (verifyRes.data.success) {
            console.log("Verification Success:", verifyRes.data.message);

            // Remove tx_ref from URL so the user doesn't re-trigger verification on refresh
            window.history.replaceState({}, document.title, "/dashboard");

            // Re-fetch status to flip the UI to PStudent
            await checkStatusWithAutoVerify();
          } else {
            console.warn("Payment verification failed:", verifyRes.data.message);
            await checkStatusWithAutoVerify();
          }
        } catch (error: any) {
          console.error("Auto-verification error:", error);

          // Handle different types of errors
          if (error.response?.status === 503 && error.response?.data?.retry) {
            // Network error - retry after a delay
            console.log("Network error detected, retrying verification...");
            setTimeout(async () => {
              try {
                const retryRes = await apiClient.get(`/payment/verify/${tx_ref}`);
                if (retryRes.data.success) {
                  window.history.replaceState({}, document.title, "/dashboard");
                  await checkStatusWithAutoVerify();
                }
              } catch (retryError) {
                console.error("Retry verification failed:", retryError);
                await checkStatusWithAutoVerify(); // Still check status in case payment was processed
              }
            }, 3000); // Retry after 3 seconds
          } else {
            // If the payment is already verified, the backend might return 400 or 404, 
            // so we check status anyway
            await checkStatusWithAutoVerify();
          }
        } finally {
          setLoading(false);
        }
      } else {
        // Normal load (no redirect from Chapa) - run auto-verify
        await checkStatusWithAutoVerify();
      }
    };

    handleReturnAndVerify();
  }, []);

  /**
   * Initialize Payment
   */
  const handlePayNow = async () => {
    if (!selectedCourseId) {
      alert("Please select a course first");
      return;
    }

    setIsRedirecting(true);

    try {
      // Hits ChapaController@initialize
      const res = await apiClient.post("/payment/initialize", {
        course_id: selectedCourseId
      });

      if (res.data.success && res.data.checkout_url) {
        // Redirect the browser to Chapa's hosted payment page
        window.location.href = res.data.checkout_url;
      } else {
        alert(res.data.message || "Failed to initialize payment");
      }
    } catch (error: any) {
      console.error("Payment error:", error);
      const errorMessage = error.response?.data?.message || "Payment initialization failed";
      alert(`Payment Error: ${errorMessage}`);
    } finally {
      setIsRedirecting(false);
    }
  };

  // --- RENDER STATES ---

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50">
        <Loader2 className="animate-spin w-10 h-10 text-blue-600 mb-4" />
        <p className="text-lg font-medium text-slate-700">Loading dashboard...</p>
        <p className="text-sm text-slate-500 italic">Checking payment status...</p>
      </div>
    );
  }

  // If status check confirms payment, show the Paid Student Dashboard
  if (isPaid) {
    return <PStudent data={dashboardData} />;
  }

  // Otherwise show the Guest (Payment Required) Dashboard
  return (
    <GuestDashboard
      paymentInfo={paymentInfo}
      onPaymentInit={handlePayNow}
      onRefresh={checkStatusWithAutoVerify}
      isRedirecting={isRedirecting}
      onCourseSelect={setSelectedCourseId}
      selectedCourseId={selectedCourseId}
    />
  );
}