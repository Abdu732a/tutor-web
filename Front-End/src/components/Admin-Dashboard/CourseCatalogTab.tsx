// src/components/Admin-Dashboard/CourseCatalogTab.tsx
import { useState, useEffect, useMemo } from "react";
import {
  Plus,
  BookOpen,
  Eye,
  Edit,
  Trash2,
  MoreVertical,
  UserCheck
} from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { apiClient } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuPortal,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import CreateCourseDialog from "./CreateCourseDialog";
import AssignTutorsDialog from "./AssignTutorsDialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "../ui/select";
import { ScrollArea } from "../ui/scroll-area";

import CourseTutorialsDialog from "./CourseTutorialsDialog";

// Types
interface Category {
  id: number;
  name: string;
  full_path: string;
  slug: string;
}

interface Tutor {
  id: number;
  name: string;
  email?: string;
  pivot?: {
    course_id: number;
    tutor_id: number;
    created_at: string;
    updated_at: string;
  };
}

interface Course {
  id: number;
  title: string;
  description?: string;
  category_id: number | null;
  category: Category | null;
  duration_hours: number;
  price_group: string | null;
  price_individual: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  tutors?: Tutor[];
}

interface CourseCatalogTabProps {
  searchQuery?: string;
  onRefresh?: () => void;
}

export default function CourseCatalogTab({
  searchQuery = "",
  onRefresh
}: CourseCatalogTabProps) {
  const { toast } = useToast();
  const [courses, setCourses] = useState<Course[]>([]);
  const [subcategories, setSubcategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [selectedCourse, setSelectedCourse] = useState<Course | null>(null);
  const [selectedSubcategory, setSelectedSubcategory] = useState<string>("all");
  const [showActiveOnly, setShowActiveOnly] = useState(false);
  const [showAssignDialog, setShowAssignDialog] = useState(false);
  const [currentCourseForAssign, setCurrentCourseForAssign] = useState<Course | null>(null);
  const [tutorSearch, setTutorSearch] = useState("");
  const [tutors, setTutors] = useState<Tutor[]>([]);
  const [loadingTutors, setLoadingTutors] = useState(false);

  const [showTutorialsDialog, setShowTutorialsDialog] = useState(false);
  const [currentCourseForTutorials, setCurrentCourseForTutorials] = useState<Course | null>(null);

  const handleViewTutorials = (course: Course) => {
    setCurrentCourseForTutorials(course);
    setShowTutorialsDialog(true);
  };

  // Fetch subcategories
  const fetchSubcategories = async () => {
    try {
      const res = await apiClient.get("/admin/categories-tree");
      if (res.data.success) {
        setSubcategories(res.data.subcategories || []);
      }
    } catch (err: any) {
      toast({
        title: "Error",
        description: "Failed to load subcategories",
        variant: "destructive",
      });
    }
  };

  // Fetch tutors
  useEffect(() => {
    const fetchTutors = async () => {
      setLoadingTutors(true);
      try {
        // Try different possible endpoints
        const endpoints = [
          '/admin/users?role=tutor',
          '/admin/tutors',
          '/tutors',
          '/admin/users/tutors'
        ];

        let tutorsData: Tutor[] = [];
        for (const endpoint of endpoints) {
          try {
            const res = await apiClient.get(endpoint);
            if (res.data.success) {
              tutorsData = res.data.data || res.data.tutors || res.data.users || [];
              if (tutorsData.length > 0) break;
            }
          } catch (e) {
            continue;
          }
        }

        setTutors(tutorsData);
      } catch (err) {
        toast({
          title: "Error",
          description: "Failed to load tutors list",
          variant: "destructive",
        });
      } finally {
        setLoadingTutors(false);
      }
    };

    fetchTutors();
  }, []);

  // Filter tutors for search
  const filteredTutors = useMemo(() => {
    const searchLower = tutorSearch.toLowerCase();
    return tutors.filter(tutor =>
      tutor.name.toLowerCase().includes(searchLower) ||
      (tutor.email && tutor.email.toLowerCase().includes(searchLower))
    );
  }, [tutors, tutorSearch]);

  // Handle assigning a single tutor
  const handleAssignSingleTutor = async (courseId: number, tutorId: number, tutorName: string) => {
    try {
      const res = await apiClient.post(`/admin/courses/${courseId}/assign-tutors`, {
        tutor_ids: [tutorId],
      });

      if (res.data.success) {
        toast({
          title: "Success",
          description: `Assigned ${tutorName} to the course`,
        });

        // Refresh course data
        await fetchCourses();
      }
    } catch (err: any) {
      toast({
        title: "Error",
        description: err.response?.data?.message || "Failed to assign tutor",
        variant: "destructive",
      });
    }
  };

  // Update the fetchCourses function
  const fetchCourses = async () => {
    try {
      setLoading(true);
      const params: any = {
        all: 'true', // Get all courses for admin
        include: 'category,tutors' // Ensure we get related data
      };
      if (searchQuery) params.search = searchQuery;

      const res = await apiClient.get("/admin/courses", { params });

      if (res.data.success) {
        // Handle both paginated and non-paginated responses
        let coursesData = [];

        if (res.data.data?.data) {
          // Paginated response
          coursesData = res.data.data.data;
        } else if (Array.isArray(res.data.data)) {
          // Direct array response
          coursesData = res.data.data;
        } else {
          coursesData = [];
        }

        // Ensure each course has proper category data
        const coursesWithCategories = coursesData.map((course: Course) => ({
          ...course,
          // Ensure tutors is always an array
          tutors: course.tutors || [],
          // Ensure category is properly set
          category: course.category || null
        }));

        setCourses(coursesWithCategories);

        // Only log success, don't show toast on every load
        console.log(`Successfully loaded ${coursesWithCategories.length} courses`);
      }
    } catch (error: any) {
      console.error('Error fetching courses:', error);
      toast({
        title: "Error",
        description: error.response?.data?.message || "Failed to load courses",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  // Initial data fetch
  useEffect(() => {
    fetchSubcategories();
    fetchCourses();
  }, [searchQuery]);

  // Filter courses based on selected filters
  const filteredCourses = useMemo(() => {
    return courses.filter(course => {
      const matchesCategory =
        selectedSubcategory === "all" ||
        course.category_id?.toString() === selectedSubcategory;

      const matchesActive = !showActiveOnly || course.is_active;

      return matchesCategory && matchesActive;
    });
  }, [courses, selectedSubcategory, showActiveOnly]);

  // Handlers
  const handleCreateCourse = () => {
    setSelectedCourse(null);
    setShowCreateDialog(true);
  };

  const handleEditCourse = (course: Course) => {
    setSelectedCourse(course);
    setShowCreateDialog(true);
  };

  const handleDeleteCourse = async (courseId: number) => {
    if (!confirm("Are you sure you want to delete this course? This action cannot be undone.")) return;

    try {
      await apiClient.delete(`/admin/courses/${courseId}`);
      toast({
        title: "Success",
        description: "Course deleted successfully",
      });
      fetchCourses();
      if (onRefresh) onRefresh();
    } catch (error: any) {
      toast({
        title: "Error",
        description: error.response?.data?.message || "Failed to delete course",
        variant: "destructive",
      });
    }
  };

  const handleViewCourse = (course: Course) => {
    toast({
      title: course.title,
      description: (
        <div className="space-y-2 text-sm">
          <p><strong>Subcategory:</strong> {course.category?.full_path || "Uncategorized"}</p>
          <p><strong>Description:</strong> {course.description || "No description"}</p>
          <p><strong>Duration:</strong> {course.duration_hours} hours</p>
          <p><strong>Group Price:</strong> {course.price_group ? `${course.price_group} ETB` : "Not set"}</p>
          <p><strong>Individual Price:</strong> {course.price_individual ? `${course.price_individual} ETB` : "Not set"}</p>
          <p><strong>Status:</strong> {course.is_active ? "Active" : "Inactive"}</p>
          <p><strong>Assigned Tutors:</strong> {course.tutors?.length || 0}</p>
          {course.tutors && course.tutors.length > 0 && (
            <div className="pl-2">
              {course.tutors.map(t => (
                <p key={t.id}>• {t.name} {t.email && `(${t.email})`}</p>
              ))}
            </div>
          )}
        </div>
      ),
      duration: 8000,
    });
  };

  const handleAssignTutors = (course: Course) => {
    setCurrentCourseForAssign(course);
    setShowAssignDialog(true);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h2 className="text-3xl font-bold text-foreground">Course Catalog</h2>
          <p className="text-muted-foreground mt-1">
            Manage all available courses and assign tutors • {courses.length} total courses
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button variant="outline" size="sm" onClick={fetchCourses} disabled={loading}>
            {loading ? "Loading..." : "Refresh List"}
          </Button>
          <Button onClick={handleCreateCourse} className="shadow-sm">
            <Plus className="mr-2 h-4 w-4" />
            Create Course
          </Button>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-card border rounded-lg p-4">
        <div className="flex flex-col sm:flex-row gap-4 items-end">
          <div className="flex-1">
            <Label htmlFor="subcategory-filter" className="mb-2 block text-sm font-medium">
              Filter by Subcategory
            </Label>
            <Select
              value={selectedSubcategory}
              onValueChange={setSelectedSubcategory}
            >
              <SelectTrigger className="w-full sm:w-[300px]">
                <SelectValue placeholder="All subcategories" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Subcategories</SelectItem>
                {subcategories.map((sub) => (
                  <SelectItem key={sub.id} value={sub.id.toString()}>
                    {sub.full_path}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex items-center space-x-2 bg-muted/50 rounded-md px-3 py-2">
            <Switch
              id="active-only"
              checked={showActiveOnly}
              onCheckedChange={setShowActiveOnly}
            />
            <Label htmlFor="active-only" className="text-sm font-medium">Active courses only</Label>
          </div>
        </div>

        {/* Filter Summary */}
        <div className="mt-3 pt-3 border-t flex items-center justify-between text-sm text-muted-foreground">
          <span>
            Showing {filteredCourses.length} of {courses.length} courses
            {selectedSubcategory !== "all" && " • Filtered by category"}
            {showActiveOnly && " • Active only"}
          </span>
          {(selectedSubcategory !== "all" || showActiveOnly) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                setSelectedSubcategory("all");
                setShowActiveOnly(false);
              }}
              className="h-auto p-1 text-xs"
            >
              Clear filters
            </Button>
          )}
        </div>
      </div>

      {/* Course Table */}
      <div className="rounded-lg border bg-card shadow-sm">
        <Table>
          <TableHeader>
            <TableRow className="bg-muted/50">
              <TableHead className="w-[35%] font-semibold">Course Details</TableHead>
              <TableHead className="font-semibold">Category</TableHead>
              <TableHead className="font-semibold">Duration</TableHead>
              <TableHead className="font-semibold">Pricing</TableHead>
              <TableHead className="font-semibold">Status</TableHead>
              <TableHead className="font-semibold">Assigned Tutors</TableHead>
              <TableHead className="text-right font-semibold">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={7} className="h-32 text-center">
                  <div className="flex flex-col items-center justify-center gap-3">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    <p className="text-muted-foreground">Loading courses...</p>
                  </div>
                </TableCell>
              </TableRow>
            ) : filteredCourses.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="h-40 text-center">
                  <div className="flex flex-col items-center justify-center gap-4 py-8">
                    <div className="rounded-full bg-muted p-4">
                      <BookOpen className="h-8 w-8 text-muted-foreground" />
                    </div>
                    <div className="space-y-2">
                      <p className="text-lg font-medium">No courses found</p>
                      <p className="text-sm text-muted-foreground max-w-sm">
                        {selectedSubcategory !== "all" || showActiveOnly
                          ? "Try adjusting your filters to see more courses"
                          : "Get started by creating your first course"}
                      </p>
                    </div>
                    <Button onClick={handleCreateCourse} className="mt-2">
                      <Plus className="mr-2 h-4 w-4" />
                      Create Course
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ) : (
              filteredCourses.map((course, index) => (
                <TableRow key={course.id} className={`hover:bg-muted/30 transition-colors ${index % 2 === 0 ? 'bg-background' : 'bg-muted/10'}`}>
                  <TableCell className="py-4">
                    <div className="space-y-2">
                      <div className="flex items-start gap-3">
                        <div className="rounded-md bg-primary/10 p-2 mt-0.5">
                          <BookOpen className="h-4 w-4 text-primary" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="font-semibold text-base leading-tight">{course.title}</p>
                          <p className="text-sm text-muted-foreground line-clamp-2 mt-1">
                            {course.description || "No description provided"}
                          </p>
                        </div>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="py-4">
                    {course.category ? (
                      <Badge variant="outline" className="font-normal px-3 py-1.5 text-xs">
                        {course.category.full_path}
                      </Badge>
                    ) : (
                      <Badge variant="secondary" className="text-xs">
                        Uncategorized
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell className="py-4">
                    <div className="flex items-center gap-2">
                      <div className="rounded-full bg-blue-100 p-1">
                        <div className="h-2 w-2 rounded-full bg-blue-600"></div>
                      </div>
                      <span className="font-medium">{course.duration_hours} hrs</span>
                    </div>
                  </TableCell>
                  <TableCell className="py-4">
                    <div className="space-y-1 text-sm">
                      <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">Group:</span>
                        <span className="font-medium">
                          {course.price_group ? `${course.price_group} ETB` : "—"}
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">1:1:</span>
                        <span className="font-medium">
                          {course.price_individual ? `${course.price_individual} ETB` : "—"}
                        </span>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="py-4">
                    <Badge
                      variant={course.is_active ? "default" : "secondary"}
                      className={course.is_active ? "bg-green-100 text-green-800 hover:bg-green-100" : ""}
                    >
                      {course.is_active ? "Active" : "Inactive"}
                    </Badge>
                  </TableCell>
                  <TableCell className="py-4">
                    {course.tutors && course.tutors.length > 0 ? (
                      <div className="space-y-1">
                        <div className="flex items-center gap-2 mb-1">
                          <div className="rounded-full bg-purple-100 p-1">
                            <div className="h-2 w-2 rounded-full bg-purple-600"></div>
                          </div>
                          <span className="text-xs font-medium text-muted-foreground">
                            {course.tutors.length} tutor{course.tutors.length > 1 ? 's' : ''}
                          </span>
                        </div>
                        <div className="flex flex-wrap gap-1">
                          {course.tutors.slice(0, 2).map(t => (
                            <Badge key={t.id} variant="secondary" className="text-xs px-2 py-0.5">
                              {t.name}
                            </Badge>
                          ))}
                          {course.tutors.length > 2 && (
                            <Badge variant="outline" className="text-xs px-2 py-0.5">
                              +{course.tutors.length - 2} more
                            </Badge>
                          )}
                        </div>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <div className="rounded-full bg-gray-100 p-1">
                          <div className="h-2 w-2 rounded-full bg-gray-400"></div>
                        </div>
                        <span className="text-xs">No tutors assigned</span>
                      </div>
                    )}
                  </TableCell>
                  <TableCell className="text-right py-4">
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 hover:bg-muted">
                          <span className="sr-only">Open menu</span>
                          <MoreVertical className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel className="font-semibold">Course Actions</DropdownMenuLabel>
                        <DropdownMenuItem onClick={() => handleViewCourse(course)} className="cursor-pointer">
                          <Eye className="mr-2 h-4 w-4" />
                          View Details
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => handleViewTutorials(course)} className="cursor-pointer">
                          <BookOpen className="mr-2 h-4 w-4" />
                          View Tutorials
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => handleEditCourse(course)} className="cursor-pointer">
                          <Edit className="mr-2 h-4 w-4" />
                          Edit Course
                        </DropdownMenuItem>

                        {/* Assign Tutor Submenu */}
                        <DropdownMenuSub>
                          <DropdownMenuSubTrigger className="cursor-pointer">
                            <UserCheck className="mr-2 h-4 w-4" />
                            Assign Tutor
                          </DropdownMenuSubTrigger>
                          <DropdownMenuPortal>
                            <DropdownMenuSubContent className="w-80 max-h-[400px]">
                              <div className="p-2 sticky top-0 bg-background z-10 border-b">
                                <Input
                                  placeholder="Search tutors..."
                                  value={tutorSearch}
                                  onChange={(e) => setTutorSearch(e.target.value)}
                                  autoFocus
                                  className="h-8"
                                />
                              </div>
                              {loadingTutors ? (
                                <div className="p-4 text-center text-sm text-muted-foreground">
                                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary mx-auto mb-2"></div>
                                  Loading tutors...
                                </div>
                              ) : filteredTutors.length === 0 ? (
                                <div className="p-4 text-center text-sm text-muted-foreground">
                                  No tutors found
                                </div>
                              ) : (
                                <ScrollArea className="max-h-[300px]">
                                  {filteredTutors.map((tutor) => (
                                    <DropdownMenuItem
                                      key={tutor.id}
                                      onClick={() => handleAssignSingleTutor(course.id, tutor.id, tutor.name)}
                                      className="cursor-pointer flex items-center gap-3 py-3 px-3 hover:bg-muted"
                                    >
                                      <div className="rounded-full bg-primary/10 p-1.5">
                                        <UserCheck className="h-3 w-3 text-primary" />
                                      </div>
                                      <div className="flex-1 min-w-0">
                                        <div className="font-medium text-sm">{tutor.name}</div>
                                        {tutor.email && (
                                          <div className="text-xs text-muted-foreground truncate">
                                            {tutor.email}
                                          </div>
                                        )}
                                      </div>
                                    </DropdownMenuItem>
                                  ))}
                                </ScrollArea>
                              )}
                            </DropdownMenuSubContent>
                          </DropdownMenuPortal>
                        </DropdownMenuSub>

                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                          className="text-destructive focus:text-destructive cursor-pointer"
                          onClick={() => handleDeleteCourse(course.id)}
                        >
                          <Trash2 className="mr-2 h-4 w-4" />
                          Delete Course
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Create / Edit Dialog */}
      <CreateCourseDialog
        open={showCreateDialog}
        onOpenChange={(open) => {
          setShowCreateDialog(open);
          if (!open) setSelectedCourse(null);
        }}
        onCourseCreated={() => {
          fetchCourses();
          if (onRefresh) onRefresh();
        }}
        editCourse={selectedCourse}
      />

      {/* Assign Tutors Dialog */}
      <AssignTutorsDialog
        open={showAssignDialog}
        onOpenChange={setShowAssignDialog}
        courseId={currentCourseForAssign?.id || 0}
        courseTitle={currentCourseForAssign?.title || ""}
        onAssigned={fetchCourses}
      />

      <CourseTutorialsDialog
        open={showTutorialsDialog}
        onOpenChange={setShowTutorialsDialog}
        courseId={currentCourseForTutorials?.id || 0}
        courseTitle={currentCourseForTutorials?.title || ""}
      />
    </div>
  );
}