// components/Student-Dashboard/DashboardLessonPlayer.tsx
import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import {
  PlayCircle,
  CheckCircle2,
  ArrowLeft,
  BookOpen,
  Clock,
  Lock,
  ChevronLeft,
  ChevronRight,
  Download,
  MessageCircle,
  Loader2,
  AlertCircle,
  Eye,
} from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { apiClient } from "@/lib/api";

/* ============================
   Types
============================ */
interface Material {
  id: number;
  original_name: string;
  mime_type: string;
  size_kb: number;
  download_url: string;
}

interface Lesson {
  id: number;
  title: string;
  description: string;
  duration: string;
  order: number;
  video_url: string | null;
  content: string | null;
  is_preview: boolean;
  is_locked: boolean;
  is_completed?: boolean;
  materials?: Material[];
}

interface Tutorial {
  id: number;
  title: string;
  instructor: string;
  category: { name: string };
}

interface DashboardLessonPlayerProps {
  tutorialId: string;
  onExitLearningMode: () => void;
}

/* ============================
   Helpers
============================ */
const getYoutubeEmbedUrl = (url: string) => {
  const match = url.match(
    /(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/,
  );
  return match ? `https://www.youtube.com/embed/${match[1]}` : null;
};

export default function DashboardLessonPlayer({
  tutorialId,
  onExitLearningMode,
}: DashboardLessonPlayerProps) {
  const { toast } = useToast();

  const [tutorial, setTutorial] = useState<Tutorial | null>(null);
  const [lessons, setLessons] = useState<Lesson[]>([]);
  const [currentLesson, setCurrentLesson] = useState<Lesson | null>(null);
  const [loading, setLoading] = useState(true);
  const [isCompleting, setIsCompleting] = useState(false);
  const [viewingPdf, setViewingPdf] = useState<string | null>(null);
  const [progress, setProgress] = useState({
    completed_lessons: 0,
    total_lessons: 0,
    percentage: 0,
  });

  /* ============================
     Fetch Data
  ============================ */
  const fetchTutorialData = async (lessonId?: number) => {
    try {
      setLoading(true);

      let response;
      if (lessonId) {
        // Fetch specific lesson
        response = await apiClient.get(
          `/tutorials/${tutorialId}/lessons/${lessonId}`
        );
      } else {
        // Fetch first lesson using "first" as lesson ID
        response = await apiClient.get(
          `/tutorials/${tutorialId}/lessons/first`
        );
      }

      const data = response.data;

      if (!data.success) {
        throw new Error(data.message);
      }

      setTutorial(data.tutorial);
      setLessons(data.sidebar_lessons || []);
      setCurrentLesson(data.lesson);
      setProgress(data.progress);
    } catch (error: any) {
      toast({
        title: "Error",
        description:
          error.response?.data?.message || "Failed to load lesson",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (tutorialId) fetchTutorialData();
  }, [tutorialId]);

  // Cleanup PDF blob URLs when component unmounts
  useEffect(() => {
    return () => {
      if (viewingPdf) {
        window.URL.revokeObjectURL(viewingPdf);
      }
    };
  }, [viewingPdf]);

  /* ============================
     Completion
  ============================ */
  const markAsCompleted = async () => {
    if (!currentLesson) return;

    try {
      setIsCompleting(true);
      const response = await apiClient.post(
        `/lessons/${currentLesson.id}/complete`
      );

      if (response.data.success) {
        toast({
          title: "Lesson Completed 🎉",
          description: "You can proceed to the next lesson",
        });
        fetchTutorialData(currentLesson.id);
      }
    } catch (error: any) {
      toast({
        title: "Error",
        description:
          error.response?.data?.message || "Failed to complete lesson",
        variant: "destructive",
      });
    } finally {
      setIsCompleting(false);
    }
  };

  /* ============================
     Material Handling
  ============================ */
  const viewPdf = async (material: Material) => {
    try {
      // Clean up previous PDF URL if exists
      if (viewingPdf) {
        window.URL.revokeObjectURL(viewingPdf);
        setViewingPdf(null);
      }

      // Extract the API path from the full URL
      const apiPath = material.download_url.replace(window.location.origin, '');

      const response = await apiClient.get(apiPath, {
        responseType: 'blob'
      });

      // Verify it's a PDF
      if (response.data.type !== 'application/pdf' && !response.headers['content-type']?.includes('pdf')) {
        throw new Error('File is not a PDF');
      }

      const blob = new Blob([response.data], { type: 'application/pdf' });
      const url = window.URL.createObjectURL(blob);
      setViewingPdf(url);
    } catch (error: any) {
      console.error('PDF view error:', error);
      toast({
        title: "View Failed",
        description: error.response?.data?.message || "Could not load the PDF file",
        variant: "destructive",
      });
    }
  };

  const downloadMaterial = async (material: Material) => {
    try {
      // Extract the API path from the full URL
      const apiPath = material.download_url.replace(window.location.origin, '');

      const response = await apiClient.get(apiPath, {
        responseType: 'blob'
      });

      const blob = new Blob([response.data]);
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = material.original_name;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);

      toast({
        title: "Download Complete",
        description: `${material.original_name} has been downloaded`,
      });
    } catch (error: any) {
      console.error('Download error:', error);
      toast({
        title: "Download Failed",
        description: error.response?.data?.message || "Could not download the file",
        variant: "destructive",
      });
    }
  };

  /* ============================
     Navigation
  ============================ */
  const currentIndex = lessons.findIndex(
    (l) => l.id === currentLesson?.id
  );

  const goToLesson = (lesson: Lesson) => {
    fetchTutorialData(lesson.id);
  };

  const goToNextLesson = () => {
    if (currentIndex < lessons.length - 1) {
      const nextLesson = lessons[currentIndex + 1];
      if (!nextLesson.is_locked) {
        goToLesson(nextLesson);
      }
    }
  };

  const goToPreviousLesson = () => {
    if (currentIndex > 0) {
      goToLesson(lessons[currentIndex - 1]);
    }
  };

  /* ============================
     Loading / Error States
  ============================ */
  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  if (!tutorial || !currentLesson) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <AlertCircle className="h-12 w-12 text-destructive" />
      </div>
    );
  }

  /* ============================
     Render
  ============================ */
  return (
    <div className="h-full flex flex-col">
      {/* Header */}
      <div className="border-b p-4 flex justify-between items-center">
        <Button variant="ghost" onClick={onExitLearningMode}>
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back
        </Button>
        <div>
          <h1 className="font-bold">{tutorial.title}</h1>
          <p className="text-sm text-muted-foreground">
            {tutorial.instructor} • {tutorial.category?.name}
          </p>
        </div>
        <div className="w-40">
          <Progress value={progress.percentage} />
        </div>
      </div>

      <div className="flex-1 grid lg:grid-cols-4 gap-6 p-6">
        {/* Sidebar */}
        <Card className="lg:col-span-1">
          <CardContent className="p-4 space-y-2">
            {lessons.map((lesson) => {
              const isCurrent = lesson.id === currentLesson.id;
              const isAccessible = !lesson.is_locked;
              return (
                <div
                  key={lesson.id}
                  onClick={() => isAccessible ? goToLesson(lesson) : null}
                  className={`p-3 rounded ${isAccessible ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'
                    } ${isCurrent
                      ? "bg-primary/10 border border-primary/30"
                      : isAccessible ? "hover:bg-muted" : ""
                    }`}
                  title={lesson.is_locked ? "Complete previous lesson to unlock" : ""}
                >
                  <div className="flex justify-between items-center">
                    <span className="text-sm font-medium">
                      {lesson.order}. {lesson.title}
                    </span>
                    {lesson.is_completed ? (
                      <CheckCircle2 className="h-4 w-4 text-green-600" />
                    ) : lesson.is_locked ? (
                      <Lock className="h-4 w-4 text-muted-foreground" />
                    ) : (
                      <PlayCircle className="h-4 w-4 text-primary" />
                    )}
                  </div>
                </div>
              );
            })}
          </CardContent>
        </Card>

        {/* Main */}
        <div className="lg:col-span-3 space-y-6">
          <div>
            <Badge>Lesson {currentLesson.order}</Badge>
            {currentLesson.is_preview && (
              <Badge variant="secondary" className="ml-2">
                Preview
              </Badge>
            )}
            <h2 className="text-2xl font-bold mt-2">
              {currentLesson.title}
            </h2>
            <p className="text-muted-foreground mt-2">
              {currentLesson.description}
            </p>
          </div>

          {/* VIDEO PLAYER (FIXED) */}
          {currentLesson.video_url ? (
            <Card>
              <div className="aspect-video bg-black">
                {currentLesson.video_url.includes("youtube") ||
                  currentLesson.video_url.includes("youtu.be") ? (
                  <iframe
                    src={getYoutubeEmbedUrl(currentLesson.video_url) ?? ""}
                    title={currentLesson.title}
                    className="w-full h-full"
                    frameBorder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                  />
                ) : (
                  <video
                    src={currentLesson.video_url}
                    controls
                    className="w-full h-full"
                  />
                )}
              </div>
            </Card>
          ) : (
            <Card>
              <CardContent className="p-10 text-center">
                <BookOpen className="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
                No video for this lesson
              </CardContent>
            </Card>
          )}

          {/* Content */}
          {currentLesson.content && (
            <Card>
              <CardContent
                className="prose max-w-none p-6"
                dangerouslySetInnerHTML={{
                  __html: currentLesson.content,
                }}
              />
            </Card>
          )}

          {/* Materials */}
          {currentLesson.materials && currentLesson.materials.length > 0 && (
            <Card>
              <CardContent className="p-6">
                <h3 className="text-lg font-semibold mb-4 flex items-center">
                  <Download className="mr-2 h-5 w-5" />
                  Lesson Materials
                </h3>
                <div className="space-y-3">
                  {currentLesson.materials.map((material) => (
                    <div
                      key={material.id}
                      className="flex items-center justify-between p-3 border rounded-lg hover:bg-muted/50"
                    >
                      <div className="flex items-center space-x-3">
                        <div className="p-2 bg-primary/10 rounded">
                          <Download className="h-4 w-4 text-primary" />
                        </div>
                        <div>
                          <p className="font-medium">{material.original_name}</p>
                          <p className="text-sm text-muted-foreground">
                            {material.mime_type} • {Math.round(material.size_kb / 1024 * 100) / 100} MB
                          </p>
                        </div>
                      </div>
                      <div className="flex space-x-2">
                        {material.mime_type === 'application/pdf' && (
                          <Dialog>
                            <DialogTrigger asChild>
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => viewPdf(material)}
                              >
                                <Eye className="mr-2 h-4 w-4" />
                                View
                              </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-5xl max-h-[95vh] w-[95vw]">
                              <DialogHeader>
                                <DialogTitle className="flex items-center">
                                  <Eye className="mr-2 h-5 w-5" />
                                  {material.original_name}
                                </DialogTitle>
                              </DialogHeader>
                              <div className="flex-1 overflow-hidden">
                                {viewingPdf ? (
                                  <iframe
                                    src={viewingPdf}
                                    className="w-full h-[75vh] border rounded"
                                    title={material.original_name}
                                    onLoad={() => {
                                      // PDF loaded successfully
                                    }}
                                    onError={() => {
                                      toast({
                                        title: "PDF Load Error",
                                        description: "Could not display the PDF file",
                                        variant: "destructive",
                                      });
                                    }}
                                  />
                                ) : (
                                  <div className="flex items-center justify-center h-[75vh] bg-muted/20 rounded">
                                    <div className="text-center">
                                      <Loader2 className="h-8 w-8 animate-spin mx-auto mb-4" />
                                      <p className="text-muted-foreground">Loading PDF...</p>
                                    </div>
                                  </div>
                                )}
                              </div>
                              <div className="flex justify-between items-center pt-4 border-t">
                                <div className="text-sm text-muted-foreground">
                                  {material.mime_type} • {Math.round(material.size_kb / 1024 * 100) / 100} MB
                                </div>
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => downloadMaterial(material)}
                                >
                                  <Download className="mr-2 h-4 w-4" />
                                  Download
                                </Button>
                              </div>
                            </DialogContent>
                          </Dialog>
                        )}
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => downloadMaterial(material)}
                        >
                          <Download className="mr-2 h-4 w-4" />
                          Download
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Actions */}
          <div className="flex justify-between pt-4 border-t">
            <Button
              variant="outline"
              disabled={currentIndex === 0}
              onClick={goToPreviousLesson}
            >
              <ChevronLeft className="mr-2 h-4 w-4" />
              Previous
            </Button>

            {!currentLesson.is_completed && (
              <Button onClick={markAsCompleted} disabled={isCompleting}>
                <CheckCircle2 className="mr-2 h-4 w-4" />
                Mark Complete
              </Button>
            )}

            <Button
              disabled={
                currentIndex === lessons.length - 1 ||
                (currentIndex < lessons.length - 1 && lessons[currentIndex + 1].is_locked)
              }
              onClick={goToNextLesson}
              title={
                currentIndex < lessons.length - 1 && lessons[currentIndex + 1].is_locked
                  ? "Complete current lesson to unlock next lesson"
                  : ""
              }
            >
              Next
              <ChevronRight className="ml-2 h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
