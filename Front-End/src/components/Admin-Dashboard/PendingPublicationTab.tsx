// components/Admin-Dashboard/PendingPublicationTab.tsx
import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { apiClient } from "@/lib/api";
import { useToast } from "@/hooks/use-toast";
import {
    CheckCircle,
    Clock,
    User,
    BookOpen,
    Eye,
    MessageSquare,
    Calendar
} from "lucide-react";

interface PendingPublicationTutorial {
    id: number;
    title: string;
    description: string;
    tutor_id: number;
    tutor_name: string;
    course_id: number;
    course_title: string;
    category?: {
        id: number;
        name: string;
    };
    price: number;
    level: string;
    status: 'pending_publication';
    lessons_count: number;
    publication_requested_at: string;
    created_at: string;
    updated_at: string;
}

export default function PendingPublicationTab({
    searchQuery,
    onRefresh
}: {
    searchQuery: string;
    onRefresh: () => void;
}) {
    const [tutorials, setTutorials] = useState<PendingPublicationTutorial[]>([]);
    const [loading, setLoading] = useState(true);
    const { toast } = useToast();

    const fetchPendingPublications = async () => {
        try {
            setLoading(true);
            const response = await apiClient.get("/admin/tutorials/pending-publication");
            if (response.data.success) {
                setTutorials(response.data.tutorials || []);
            }
        } catch (error: any) {
            console.error('Fetch pending publications error:', error);
            toast({
                title: "Error",
                description: "Failed to fetch tutorials pending publication",
                variant: "destructive",
            });
        } finally {
            setLoading(false);
        }
    };

    const handlePublishTutorial = async (tutorialId: number) => {
        try {
            await apiClient.post(`/admin/tutorials/${tutorialId}/publish`);
            toast({
                title: "Success",
                description: "Tutorial published successfully! Tutor has been notified.",
            });
            fetchPendingPublications();
            onRefresh();
        } catch (error: any) {
            toast({
                title: "Error",
                description: error.response?.data?.message || "Failed to publish tutorial",
                variant: "destructive",
            });
        }
    };

    useEffect(() => {
        fetchPendingPublications();
    }, []);

    const filteredTutorials = tutorials.filter(tutorial =>
        tutorial.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
        tutorial.tutor_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        tutorial.course_title.toLowerCase().includes(searchQuery.toLowerCase())
    );

    if (loading) {
        return <div className="text-center py-8">Loading tutorials pending publication...</div>;
    }

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <div>
                    <h2 className="text-2xl font-bold">Pending Publication Requests</h2>
                    <p className="text-muted-foreground">
                        Tutorials with lessons ready for publication, requested by tutors
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button onClick={fetchPendingPublications} variant="outline">
                        Refresh
                    </Button>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Pending Publication
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {tutorials.length}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Tutorials awaiting publication
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Total Lessons
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {tutorials.reduce((sum, t) => sum + t.lessons_count, 0)}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Ready for student access
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            Unique Tutors
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">
                            {new Set(tutorials.map(t => t.tutor_id)).size}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Requesting publication
                        </p>
                    </CardContent>
                </Card>
            </div>

            {/* Tutorials List */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MessageSquare className="w-5 h-5" />
                        Publication Requests
                    </CardTitle>
                    <CardDescription>
                        Tutors have added lessons and requested publication for these tutorials
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {filteredTutorials.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                            <MessageSquare className="w-12 h-12 mx-auto mb-4 opacity-50" />
                            <p>No tutorials pending publication</p>
                            <p className="text-sm">Tutors will request publication after adding lessons</p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {filteredTutorials.map((tutorial) => (
                                <div
                                    key={tutorial.id}
                                    className="flex flex-col p-4 border rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/20 dark:to-indigo-950/20"
                                >
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2">
                                                <BookOpen className="w-4 h-4 text-blue-600" />
                                                <span className="font-medium">{tutorial.title}</span>
                                                <Badge variant="secondary" className="bg-blue-100 text-blue-800">
                                                    <MessageSquare className="w-3 h-3 mr-1" />
                                                    Publication Requested
                                                </Badge>
                                            </div>

                                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                <div className="flex items-center gap-1">
                                                    <User className="w-3 h-3" />
                                                    <span>Tutor: {tutorial.tutor_name}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <BookOpen className="w-3 h-3" />
                                                    <span>Course: {tutorial.course_title}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <CheckCircle className="w-3 h-3 text-green-600" />
                                                    <span className="text-green-600 font-medium">
                                                        {tutorial.lessons_count} lesson{tutorial.lessons_count !== 1 ? 's' : ''} ready
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="w-3 h-3" />
                                                    <span>Requested: {new Date(tutorial.publication_requested_at).toLocaleDateString()}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <span>Level: {tutorial.level}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <span>Price: ${tutorial.price}</span>
                                                </div>
                                                {tutorial.category && (
                                                    <Badge variant="outline">
                                                        {tutorial.category.name}
                                                    </Badge>
                                                )}
                                            </div>

                                            <p className="text-sm text-muted-foreground line-clamp-2">
                                                {tutorial.description}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                onClick={() => handlePublishTutorial(tutorial.id)}
                                                size="sm"
                                                className="gap-1 bg-green-600 hover:bg-green-700"
                                            >
                                                <Eye className="w-3 h-3" />
                                                Publish Now
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Help Text */}
            {tutorials.length > 0 && (
                <Card className="bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800">
                    <CardContent className="pt-6">
                        <div className="flex items-start gap-3">
                            <MessageSquare className="w-5 h-5 text-blue-600 mt-0.5" />
                            <div className="space-y-1">
                                <h4 className="font-medium text-blue-900 dark:text-blue-100">
                                    Publication Workflow
                                </h4>
                                <p className="text-sm text-blue-700 dark:text-blue-300">
                                    These tutorials have been approved and tutors have added lessons.
                                    They sent you messages requesting publication. Once published,
                                    students can enroll and access the content.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}