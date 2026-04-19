import { useState, useEffect } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useToast } from "@/hooks/use-toast";
import { apiClient } from "@/lib/api";

const ResetPassword = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const { toast } = useToast();

    const [formData, setFormData] = useState({
        password: "",
        password_confirmation: "",
    });
    const [loading, setLoading] = useState(false);
    const [verifying, setVerifying] = useState(true);
    const [tokenValid, setTokenValid] = useState(false);

    const token = searchParams.get('token');
    const email = searchParams.get('email');

    useEffect(() => {
        if (!token || !email) {
            toast({
                title: "Invalid Reset Link",
                description: "This password reset link is invalid or incomplete.",
                variant: "destructive"
            });
            navigate('/login');
            return;
        }

        // Verify token on component mount
        verifyToken();
    }, [token, email, navigate, toast]);

    const verifyToken = async () => {
        try {
            const response = await apiClient.post('/auth/verify-reset-token', {
                token,
                email
            });

            if (response.data.success) {
                setTokenValid(true);
            } else {
                toast({
                    title: "Invalid or Expired Link",
                    description: response.data.message,
                    variant: "destructive"
                });
                navigate('/login');
            }
        } catch (error: any) {
            toast({
                title: "Invalid Reset Link",
                description: error.response?.data?.message || "This reset link is invalid or has expired.",
                variant: "destructive"
            });
            navigate('/login');
        } finally {
            setVerifying(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (formData.password !== formData.password_confirmation) {
            toast({
                title: "Password Mismatch",
                description: "Passwords do not match. Please try again.",
                variant: "destructive"
            });
            return;
        }

        if (formData.password.length < 8) {
            toast({
                title: "Password Too Short",
                description: "Password must be at least 8 characters long.",
                variant: "destructive"
            });
            return;
        }

        setLoading(true);

        try {
            const response = await apiClient.post('/auth/reset-password', {
                token,
                email,
                password: formData.password,
                password_confirmation: formData.password_confirmation
            });

            if (response.data.success) {
                toast({
                    title: "Password Reset Successful",
                    description: response.data.message,
                });

                // Redirect to login after 2 seconds
                setTimeout(() => {
                    navigate('/login');
                }, 2000);
            } else {
                toast({
                    title: "Reset Failed",
                    description: response.data.message,
                    variant: "destructive"
                });
            }
        } catch (error: any) {
            toast({
                title: "Reset Failed",
                description: error.response?.data?.message || "Failed to reset password. Please try again.",
                variant: "destructive"
            });
        } finally {
            setLoading(false);
        }
    };

    if (verifying) {
        return (
            <div className="min-h-screen flex flex-col">
                <Navbar />
                <main className="flex-1 flex items-center justify-center py-20 px-4">
                    <div className="w-full max-w-md">
                        <div className="bg-card rounded-lg shadow-elegant p-8 border border-border text-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                            <p className="text-muted-foreground">Verifying reset link...</p>
                        </div>
                    </div>
                </main>
                <Footer />
            </div>
        );
    }

    if (!tokenValid) {
        return null; // Will redirect to login
    }

    return (
        <div className="min-h-screen flex flex-col">
            <Navbar />
            <main className="flex-1 flex items-center justify-center py-20 px-4">
                <div className="w-full max-w-md">
                    <div className="bg-card rounded-lg shadow-elegant p-8 border border-border">
                        <h1 className="text-3xl font-bold text-center mb-6">Reset Password</h1>

                        <div className="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <p className="text-sm text-blue-800 dark:text-blue-200">
                                <strong>Email:</strong> {email}
                            </p>
                            <p className="text-sm text-blue-600 dark:text-blue-300 mt-1">
                                Enter your new password below.
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="password">New Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    placeholder="Enter new password (min 8 characters)"
                                    value={formData.password}
                                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                    required
                                    minLength={8}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">Confirm New Password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    placeholder="Confirm new password"
                                    value={formData.password_confirmation}
                                    onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                                    required
                                    minLength={8}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={loading}
                                variant="default"
                            >
                                {loading ? "Resetting Password..." : "Reset Password"}
                            </Button>
                        </form>

                        <div className="mt-6 text-center">
                            <p className="text-muted-foreground text-sm">
                                Remember your password?{" "}
                                <button
                                    onClick={() => navigate('/login')}
                                    className="text-primary hover:underline font-semibold"
                                >
                                    Back to login
                                </button>
                            </p>
                        </div>
                    </div>
                </div>
            </main>
            <Footer />
        </div>
    );
};

export default ResetPassword;