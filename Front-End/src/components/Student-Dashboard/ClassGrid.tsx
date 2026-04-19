// components/Student-Dashboard/ClassGrid.tsx
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { PlayCircle, Clock, BookOpen, AlertCircle } from "lucide-react";
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

interface ClassGridProps {
  tutorials: EnrolledTutorial[];
  onChatWithTutor?: (tutorId: number) => void;
  onTutorialClick: (tutorial: EnrolledTutorial) => void;
}

export default function ClassGrid({
  tutorials,
  onChatWithTutor,
  onTutorialClick
}: ClassGridProps) {
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
    console.error('ClassGrid: tutorials is not an array:', tutorials);
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

      const now = new Date();
      const diffTime = Math.abs(now.getTime() - date.getTime());
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays === 1) return "Yesterday";
      if (diffDays < 7) return `${diffDays} days ago`;
      if (diffDays < 30) return `${Math.ceil(diffDays / 7)} weeks ago`;
      return `${Math.ceil(diffDays / 30)} months ago`;
    } catch (error) {
      console.warn('Error formatting date:', dateString, error);
      return "Recently";
    }
  };

  const handleChatClick = (e: React.MouseEvent, tutorId: number | null) => {
    e.stopPropagation();
    if (onChatWithTutor && tutorId) {
      onChatWithTutor(tutorId);
    }
  };

  return (
    <ErrorBoundary>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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
              className="cursor-pointer hover:shadow-lg transition-all duration-200 group bg-card"
              onClick={(e) => handleCardClick(safeTutorial, e)}
            >
              <CardContent className="p-0">
                {/* Tutorial Image */}
                <div className="relative h-40 overflow-hidden rounded-t-lg bg-muted">
                  {safeTutorial.image ? (
                    <img
                      src={safeTutorial.image}
                      alt={safeTutorial.title}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                      onError={(e) => {
                        // Fallback to placeholder if image fails to load
                        e.currentTarget.style.display = 'none';
                        e.currentTarget.nextElementSibling?.classList.remove('hidden');
                      }}
                    />
                  ) : null}
                  <div className={`w-full h-full flex items-center justify-center bg-gradient-to-br from-primary/20 to-primary/10 ${safeTutorial.image ? 'hidden' : ''}`}>
                    <BookOpen className="w-12 h-12 text-primary/60" />
                  </div>
                  <div className="absolute inset-0 bg-black/20 group-hover:bg-black/10 transition-colors" />

                  {/* Progress Badge */}
                  <div className="absolute top-3 right-3">
                    <div className={`px-2 py-1 rounded-full text-xs font-medium ${safeTutorial.is_completed
                      ? 'bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400'
                      : 'bg-primary/20 text-primary dark:text-primary-foreground'
                      }`}>
                      {safeTutorial.is_completed ? 'Completed' : `${Math.round(safeTutorial.progress_percentage)}%`}
                    </div>
                  </div>

                  {/* Category Badge */}
                  <div className="absolute top-3 left-3">
                    <div className="px-2 py-1 rounded-full bg-background/90 text-xs font-medium text-foreground">
                      {safeTutorial.category}
                    </div>
                  </div>
                </div>

                {/* Tutorial Info */}
                <div className="p-4">
                  <h3 className="font-semibold text-lg mb-2 line-clamp-2 text-foreground group-hover:text-primary transition-colors">
                    {safeTutorial.title}
                  </h3>

                  <p className="text-sm text-muted-foreground mb-3 line-clamp-2">
                    {safeTutorial.description}
                  </p>

                  <div className="flex items-center justify-between text-sm text-muted-foreground mb-3">
                    <span className="flex items-center gap-1">
                      <BookOpen className="w-4 h-4" />
                      {safeTutorial.completed_lessons}/{safeTutorial.total_lessons} lessons
                    </span>
                    <span className="flex items-center gap-1">
                      <Clock className="w-4 h-4" />
                      {formatLastAccessed(safeTutorial.last_accessed)}
                    </span>
                  </div>

                  {/* Progress Bar */}
                  <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">Progress</span>
                      <span className="font-medium text-foreground">{Math.round(safeTutorial.progress_percentage)}%</span>
                    </div>
                    <Progress
                      value={safeTutorial.progress_percentage}
                      className="h-2 bg-muted"
                    />
                  </div>

                  {/* Instructor & Action */}
                  <div className="flex items-center justify-between mt-4 pt-4 border-t border-border">
                    <div>
                      <p className="text-sm font-medium text-foreground">{safeTutorial.instructor}</p>
                      <p className="text-xs text-muted-foreground">Instructor</p>
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
                      {safeTutorial.is_completed ? 'Review' : 'Continue'}
                    </Button>
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