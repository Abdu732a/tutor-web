import { useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';

export default function DashboardRedirect() {
    const { user, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-background">
                <div className="text-lg text-foreground">Loading...</div>
            </div>
        );
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    // Redirect based on user role
    switch (user.role) {
        case 'student':
            return <Navigate to="/student" replace />;
        case 'tutor':
            return <Navigate to="/tutor" replace />;
        case 'admin':
            return <Navigate to="/admin" replace />;
        case 'super_admin':
            return <Navigate to="/super-admin" replace />;
        default:
            return <Navigate to="/" replace />;
    }
}