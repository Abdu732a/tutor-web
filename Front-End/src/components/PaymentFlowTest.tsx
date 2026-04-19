import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { apiClient } from '@/lib/api';
import { useToast } from '@/hooks/use-toast';
import {
    CreditCard,
    CheckCircle,
    AlertCircle,
    Loader2,
    RefreshCw,
    DollarSign
} from 'lucide-react';

interface PaymentFlowTestProps {
    onComplete?: () => void;
}

export default function PaymentFlowTest({ onComplete }: PaymentFlowTestProps) {
    const [loading, setLoading] = useState(false);
    const [paymentStatus, setPaymentStatus] = useState<any>(null);
    const [selectedCourse, setSelectedCourse] = useState<any>(null);
    const [testResults, setTestResults] = useState<string[]>([]);
    const { toast } = useToast();

    const addTestResult = (message: string, success: boolean = true) => {
        const icon = success ? '✅' : '❌';
        setTestResults(prev => [...prev, `${icon} ${message}`]);
    };

    const testPaymentStatus = async () => {
        try {
            setLoading(true);
            addTestResult('Testing payment status endpoint...');

            const response = await apiClient.get('/student/payment-status');

            if (response.data.success) {
                setPaymentStatus(response.data);
                addTestResult(`Payment status: ${response.data.is_paid ? 'PAID' : 'UNPAID'}`);
                addTestResult(`Amount due: ${response.data.amount_due} ETB`);

                if (response.data.selected_course) {
                    setSelectedCourse(response.data.selected_course);
                    addTestResult(`Selected course: ${response.data.selected_course.title}`);
                } else {
                    addTestResult('No course selected yet', false);
                }
            } else {
                addTestResult('Payment status check failed', false);
            }
        } catch (error: any) {
            addTestResult(`Error: ${error.response?.data?.message || error.message}`, false);
        } finally {
            setLoading(false);
        }
    };

    const testCourseSelection = async () => {
        try {
            setLoading(true);
            addTestResult('Testing course selection...');

            // First get available courses
            const coursesResponse = await apiClient.get('/payment/available-courses');

            if (coursesResponse.data.success && coursesResponse.data.courses.length > 0) {
                const firstCourse = coursesResponse.data.courses[0];
                addTestResult(`Found ${coursesResponse.data.courses.length} available courses`);

                // Select the first course
                const selectResponse = await apiClient.post('/payment/select-course', {
                    course_id: firstCourse.id
                });

                if (selectResponse.data.success) {
                    addTestResult(`Course selected: ${firstCourse.title}`);
                    setSelectedCourse(firstCourse);

                    // Refresh payment status to see updated price
                    await testPaymentStatus();
                } else {
                    addTestResult('Course selection failed', false);
                }
            } else {
                addTestResult('No courses available', false);
            }
        } catch (error: any) {
            addTestResult(`Error: ${error.response?.data?.message || error.message}`, false);
        } finally {
            setLoading(false);
        }
    };

    const testPaymentInitialization = async () => {
        if (!selectedCourse) {
            addTestResult('Please select a course first', false);
            return;
        }

        try {
            setLoading(true);
            addTestResult('Testing payment initialization...');

            const response = await apiClient.post('/payment/initialize', {
                course_id: selectedCourse.id
            });

            if (response.data.success && response.data.checkout_url) {
                addTestResult('Payment initialization successful');
                addTestResult(`Checkout URL generated: ${response.data.checkout_url.substring(0, 50)}...`);

                toast({
                    title: "Payment Ready",
                    description: "Payment initialization successful. In production, you would be redirected to Chapa.",
                });
            } else {
                addTestResult('Payment initialization failed', false);
            }
        } catch (error: any) {
            addTestResult(`Error: ${error.response?.data?.message || error.message}`, false);
        } finally {
            setLoading(false);
        }
    };

    const clearResults = () => {
        setTestResults([]);
        setPaymentStatus(null);
        setSelectedCourse(null);
    };

    return (
        <div className="max-w-4xl mx-auto p-6 space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <CreditCard className="h-5 w-5" />
                        Payment Flow Test Suite
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <Button
                            onClick={testPaymentStatus}
                            disabled={loading}
                            variant="outline"
                            className="w-full"
                        >
                            {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle className="mr-2 h-4 w-4" />}
                            Test Status
                        </Button>

                        <Button
                            onClick={testCourseSelection}
                            disabled={loading}
                            variant="outline"
                            className="w-full"
                        >
                            {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <DollarSign className="mr-2 h-4 w-4" />}
                            Select Course
                        </Button>

                        <Button
                            onClick={testPaymentInitialization}
                            disabled={loading || !selectedCourse}
                            variant="outline"
                            className="w-full"
                        >
                            {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CreditCard className="mr-2 h-4 w-4" />}
                            Test Payment
                        </Button>

                        <Button
                            onClick={clearResults}
                            variant="ghost"
                            className="w-full"
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Current Status */}
            {paymentStatus && (
                <Card>
                    <CardHeader>
                        <CardTitle>Current Payment Status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="text-center">
                                <Badge variant={paymentStatus.is_paid ? "default" : "secondary"}>
                                    {paymentStatus.is_paid ? "PAID" : "UNPAID"}
                                </Badge>
                                <p className="text-sm text-muted-foreground mt-1">Payment Status</p>
                            </div>

                            <div className="text-center">
                                <p className="text-2xl font-bold">{paymentStatus.amount_due}</p>
                                <p className="text-sm text-muted-foreground">ETB Amount Due</p>
                            </div>

                            <div className="text-center">
                                {selectedCourse ? (
                                    <>
                                        <p className="font-medium">{selectedCourse.title}</p>
                                        <p className="text-sm text-muted-foreground">Selected Course</p>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-muted-foreground">No Course</p>
                                        <p className="text-sm text-muted-foreground">Selected</p>
                                    </>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Test Results */}
            {testResults.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Test Results</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2 max-h-96 overflow-y-auto">
                            {testResults.map((result, index) => (
                                <div
                                    key={index}
                                    className={`p-2 rounded text-sm font-mono ${result.startsWith('✅')
                                            ? 'bg-green-50 text-green-800'
                                            : result.startsWith('❌')
                                                ? 'bg-red-50 text-red-800'
                                                : 'bg-gray-50 text-gray-800'
                                        }`}
                                >
                                    {result}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Instructions */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <AlertCircle className="h-5 w-5" />
                        Test Instructions
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ol className="list-decimal list-inside space-y-2 text-sm">
                        <li>Click "Test Status" to check current payment status</li>
                        <li>Click "Select Course" to choose a course and see price calculation</li>
                        <li>Click "Test Payment" to initialize payment (won't actually charge)</li>
                        <li>Check the test results for detailed feedback</li>
                    </ol>

                    <div className="mt-4 p-4 bg-blue-50 rounded-lg">
                        <p className="text-sm text-blue-800">
                            <strong>Note:</strong> This is a test component. In production, the payment initialization
                            would redirect to Chapa's payment page. Make sure your database is running and
                            you have valid Chapa API keys configured.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}