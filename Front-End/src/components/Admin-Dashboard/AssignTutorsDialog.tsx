// src/components/Admin-Dashboard/AssignTutorsDialog.tsx
import { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { apiClient } from "@/lib/api";
import { toast } from "sonner";

interface Tutor {
  id: number;
  name: string;
  email?: string;
  subjects?: Array<{
    subject_name: string;
    specialization: string;
    level: string;
  }>;
  experience_years?: number;
  hourly_rate?: number;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  courseId: number;
  courseTitle: string;
  onAssigned: () => void;
}

export default function AssignTutorsDialog({
  open,
  onOpenChange,
  courseId,
  courseTitle,
  onAssigned,
}: Props) {
  const [tutors, setTutors] = useState<Tutor[]>([]);
  const [selectedTutors, setSelectedTutors] = useState<number[]>([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);
  const [assigning, setAssigning] = useState(false);

  // Fetch filtered tutors based on course category
  useEffect(() => {
    if (open && courseId) {
      const fetchTutors = async () => {
        setLoading(true);
        try {
          console.log(`🔍 Fetching filtered tutors for course ID: ${courseId}`);

          // Use the new filtered API endpoint
          const res = await apiClient.get(`/admin/courses/${courseId}/available-tutors`);
          console.log('✅ Filtered tutors API response:', res.data);

          if (res.data.success) {
            // Transform the response to match the expected Tutor interface
            const transformedTutors = res.data.data.map((tutor: any) => ({
              id: tutor.id,
              name: tutor.name,
              email: tutor.email,
              subjects: tutor.subjects,
              experience_years: tutor.experience_years,
              hourly_rate: tutor.hourly_rate
            }));
            setTutors(transformedTutors);
            console.log(`📊 Loaded ${transformedTutors.length} filtered tutors`);

            // Show matching info in a toast
            if (res.data.matching_criteria) {
              const criteria = res.data.matching_criteria;
              const criteriaText = criteria.parent_category
                ? `${criteria.subcategory} (${criteria.parent_category})`
                : criteria.subcategory;
              toast.info(`Showing ${transformedTutors.length} tutors matching: ${criteriaText}`);
            }
          }
        } catch (err: any) {
          console.error('❌ Failed to load filtered tutors:', err);
          console.error('Error details:', err.response?.data);

          // Fallback to all tutors if filtering fails
          try {
            console.log('🔄 Falling back to all tutors...');
            const fallbackRes = await apiClient.get(`/admin/tutors?course_id=${courseId}`);
            if (fallbackRes.data.success) {
              setTutors(fallbackRes.data.tutors || []);
              console.log(`📊 Loaded ${fallbackRes.data.tutors?.length || 0} tutors (fallback)`);

              if (fallbackRes.data.filtered) {
                toast.success(`✅ Fallback filtering worked! Showing ${fallbackRes.data.total_filtered} tutors for ${fallbackRes.data.course_info?.category}`);
              } else {
                toast.warning(`⚠️ Showing all ${fallbackRes.data.total_all} tutors (filtering unavailable)`);
              }
            }
          } catch (fallbackErr) {
            console.error('❌ Fallback also failed:', fallbackErr);
            toast.error("Failed to load tutors");
          }
        } finally {
          setLoading(false);
        }
      };
      fetchTutors();
    }
  }, [open, courseId]);

  // Filter tutors by search
  const filteredTutors = tutors.filter(tutor =>
    tutor.name.toLowerCase().includes(search.toLowerCase()) ||
    (tutor.email && tutor.email.toLowerCase().includes(search.toLowerCase()))
  );

  // Toggle tutor selection
  const toggleTutor = (tutorId: number) => {
    setSelectedTutors(prev =>
      prev.includes(tutorId)
        ? prev.filter(id => id !== tutorId)
        : [...prev, tutorId]
    );
  };

  const handleAssign = async () => {
    if (selectedTutors.length === 0) {
      toast.warning("Select at least one tutor");
      return;
    }

    setAssigning(true);
    try {
      const res = await apiClient.post(`/admin/courses/${courseId}/assign-tutors`, {
        tutor_ids: selectedTutors,
      });

      if (res.data.success) {
        toast.success(`Assigned ${selectedTutors.length} tutor(s) to "${courseTitle}"`);
        onAssigned();
        onOpenChange(false);
        setSelectedTutors([]); // reset
      }
    } catch (err: any) {
      toast.error(err.response?.data?.message || "Failed to assign tutors");
    } finally {
      setAssigning(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[600px] max-h-[80vh]">
        <DialogHeader>
          <DialogTitle>Assign Tutors to "{courseTitle}" (Course ID: {courseId})</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-4">
          {/* Search */}
          <div>
            <Input
              placeholder="Search tutors by name or email..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>

          {/* Tutors list */}
          {loading ? (
            <div className="text-center py-6">Loading tutors...</div>
          ) : filteredTutors.length === 0 ? (
            <div className="text-center py-6 text-muted-foreground">
              No tutors found
            </div>
          ) : (
            <>
              <div className="text-sm text-muted-foreground mb-2">
                Showing {filteredTutors.length} tutors
              </div>
              <ScrollArea className="h-[400px] border rounded-md">
                <div className="space-y-2 p-4">
                  {filteredTutors.map(tutor => (
                    <div
                      key={tutor.id}
                      className="flex items-start space-x-3 p-3 hover:bg-accent rounded cursor-pointer border-b last:border-b-0"
                      onClick={() => toggleTutor(tutor.id)}
                    >
                      <Checkbox
                        checked={selectedTutors.includes(tutor.id)}
                        onCheckedChange={() => toggleTutor(tutor.id)}
                        className="mt-1"
                      />
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between mb-1">
                          <Label className="font-medium text-sm cursor-pointer">{tutor.name}</Label>
                          {tutor.experience_years && (
                            <span className="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded flex-shrink-0">
                              {tutor.experience_years} years exp
                            </span>
                          )}
                        </div>
                        {tutor.email && (
                          <p className="text-xs text-muted-foreground mb-1">{tutor.email}</p>
                        )}
                        {tutor.subjects && tutor.subjects.length > 0 && (
                          <div className="mb-1">
                            <p className="text-xs text-green-600 font-medium">
                              📖 {tutor.subjects.map(s =>
                                s.specialization ? `${s.subject_name} (${s.specialization})` : s.subject_name
                              ).join(', ')}
                            </p>
                          </div>
                        )}
                        {tutor.hourly_rate && (
                          <p className="text-xs text-gray-500">💰 ${tutor.hourly_rate}/hour</p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </ScrollArea>
            </>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleAssign} disabled={assigning || selectedTutors.length === 0}>
            {assigning ? "Assigning..." : `Assign ${selectedTutors.length} Tutor${selectedTutors.length !== 1 ? 's' : ''}`}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}