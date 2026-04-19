// components/Student-Dashboard/ClassList.tsx
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { PlayCircle, BookOpen, Calendar, AlertCircle } from "lucide-react";
import { useNavigate } from "react-router-dom";
import ErrorBoundary from "@/components/ErrorBoundary";

interface EnrolledTutorial {
  id: number;
  title: string;
  description: string;
  category: string;
  image: string | null;
  instructor: string;
  progress_percentage: number;
  completed_lessons: number;
  total_lessons: number;
  last_accessed: string;
  is_completed: boolean;
  tutor_id: number | null;
  tutor_name: string;
}

interface ClassListProps {
  tutorials: EnrolledTutorial[];
  onChatWithTutor?: (tutorId: number) => void;
  onTutorialClick: (tutorial: EnrolledTutorial) => void;
}

export default function ClassList({
  tutorials,
  onChatWithTutor,
  onTutorialClick
}: ClassListProps) {
  const navigate = useNavigate();

  // Add comprehensive safety checks
  if (!tutorials) {
    return (
      <div className="text-center py-8">
        <AlertCircle className="mx-auto h-12 w-12 text-yellow-500 mb-4" />
        <p className="text-muted-foreground">Loading tutorials...</p>
      </div>
    );
  }

  if (!Array.isArray(tutorials)) {
    console.error('ClassList: tutorials is not an array:', tutorials);
    return (
      <div className="text-center py-8">
        <AlertCircle className="mx-auto h-12 w-12 text-red-500 mb-4" />
        <p className="text-muted-foreground">Error loading tutorials data</p>
      </div>
    );
  }

  if (tutorials.length === 0) {
    return (
      <div className="text-center py-8">
        <BookOpen className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
        <p className="text-muted-foreground">No tutorials found</p>
      </div>
    );
  }

  const handleCardClick = (tutorial: EnrolledTutorial, e: React.MouseEvent) => {
    // Only navigate if click is not on the button
    if (!(e.target as HTMLElement).closest('button')) {
      onTutorialClick(tutorial);
    }
  };

  const formatLastAccessed = (dateString: string) => {
    if (!dateString) return "Recently";

    try {
      const date = new Date(dateString);
      if (isNaN(date.getTime())) return "Recently";

      return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    } catch (error) {
      console.warn('Error formatting date:', dateString, error);
      return "Recently";
    }
  };

  const getStatusText = (tutorial: EnrolledTutorial) => {
    if (tutorial.is_completed) return "Completed";
    if (tutorial.progress_percentage > 0) return "In Progress";
    return "Not Started";
  };

  const getStatusColor = (tutorial: EnrolledTutorial) => {
    if (tutorial.is_completed) return "text-green-600 dark:text-green-400 bg-green-100 dark:bg-green-500/20";
    if (tutorial.progress_percentage > 0) return "text-primary bg-primary/20";
    return "text-muted-foreground bg-muted";
  };

  const handleChatClick = (e: React.MouseEvent, tutorId: number | null) => {
    e.stopPropagation();
    if (onChatWithTutor && tutorId) {
      onChatWithTutor(tutorId);
    }
  };

  return (
    <ErrorBoundary>
      <div className="space-y-4">
        {tutorials.map((tutorial) => {
          // Add safety checks for tutorial data
          if (!tutorial || !tutorial.id) {
            console.warn('Invalid tutorial data:', tutorial);
            return null;
          }

          // Ensure all required fields have default values
          const safeTutorial = {
            id: tutorial.id,
            title: tutorial.title || 'Untitled Course',
            description: tutorial.description || 'No description available',
            category: tutorial.category || 'General',
            image: tutorial.image,
            instructor: tutorial.instructor || 'Instructor',
            progress_percentage: tutorial.progress_percentage || 0,
            completed_lessons: tutorial.completed_lessons || 0,
            total_lessons: tutorial.total_lessons || 0,
            last_accessed: tutorial.last_accessed || new Date().toISOString(),
            is_completed: tutorial.is_completed || false,
            tutor_id: tutorial.tutor_id,
            tutor_name: tutorial.tutor_name || 'Tutor'
          };

          return (
            <Card
              key={safeTutorial.id}
              className="cursor-pointer hover:shadow-lg transition-all duration-200 bg-card"
              onClick={(e) => handleCardClick(safeTutorial, e)}
            >
              <CardContent className="p-6">
                <div className="flex items-start gap-4">
                  {/* Tutorial Image */}
                  <div className="shrink-0 w-24 h-24 rounded-lg overflow-hidden border border-border bg-muted">
                    {safeTutorial.image ? (
                      <img
                        src={safeTutorial.image}
                        alt={safeTutorial.title}
                        className="w-full h-full object-cover"
                        onError={(e) => {
                          // Fallback to placeholder if image fails to load
                          e.currentTarget.style.display = 'none';
                          e.currentTarget.nextElementSibling?.classList.remove('hidden');
                        }}
                      />
                    ) : null}
                    <div className={`w-full h-full flex items-center justify-center bg-gradient-to-br from-primary/20 to-primary/10 ${safeTutorial.image ? 'hidden' : ''}`}>
                      <BookOpen className="w-6 h-6 text-primary/60" />
                    </div>
                  </div>

                  {/* Tutorial Details */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between mb-2">
                      <div>
                        <h3 className="font-semibold text-lg mb-1 text-foreground hover:text-primary transition-colors">
                          {safeTutorial.title}
                        </h3>
                        <p className="text-sm text-muted-foreground mb-2 line-clamp-2">
                          {safeTutorial.description}
                        </p>
                      </div>
                      <div className={`px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(safeTutorial)}`}>
                        {getStatusText(safeTutorial)}
                      </div>
                    </div>

                    <div className="flex items-center gap-4 text-sm text-muted-foreground mb-3">
                      <span className="flex items-center gap-1">
                        <BookOpen className="w-4 h-4" />
                        {safeTutorial.completed_lessons}/{safeTutorial.total_lessons} lessons
                      </span>
                      <span className="flex items-center gap-1">
                        <Calendar className="w-4 h-4" />
                        {formatLastAccessed(safeTutorial.last_accessed)}
                      </span>
                      <span className="text-sm font-medium text-foreground">
                        {safeTutorial.category}
                      </span>
                    </div>

                    {/* Progress Bar */}
                    <div className="space-y-2">
                      <div className="flex justify-between text-sm">
                        <span className="text-muted-foreground">Progress</span>
                        <span className="font-medium text-foreground">{Math.round(safeTutorial.progress_percentage)}%</span>
                      </div>
                      <Progress value={safeTutorial.progress_percentage} className="h-2 bg-muted" />
                    </div>

                    {/* Instructor & Actions */}
                    <div className="flex items-center justify-between mt-3">
                      <div>
                        <p className="text-sm text-muted-foreground">Instructor</p>
                        <p className="font-medium text-foreground">{safeTutorial.instructor}</p>
                        {safeTutorial.tutor_id && (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 px-2 mt-1 text-xs"
                            onClick={(e) => handleChatClick(e, safeTutorial.tutor_id)}
                          >
                            Message Tutor
                          </Button>
                        )}
                      </div>
                      <Button
                        size="sm"
                        className="gap-2"
                        onClick={(e) => {
                          e.stopPropagation();
                          onTutorialClick(safeTutorial);
                        }}
                      >
                        <PlayCircle className="w-4 h-4" />
                        {safeTutorial.is_completed ? 'Review' : 'Continue Learning'}
                      </Button>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        }).filter(Boolean)}
      </div>
    </ErrorBoundary>
  );
}