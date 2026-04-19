<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Message;
use App\Models\User;

class MessageController extends Controller
{
    /**
     * Get conversations for current user
     */
    public function conversations(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role === 'tutor') {
                // Get students enrolled in courses that have tutorials by this tutor
                $studentIds = DB::table('enrollments')
                    ->join('courses', 'enrollments.course_id', '=', 'courses.id')
                    ->join('tutorials', 'tutorials.course_id', '=', 'courses.id')
                    ->where('tutorials.tutor_id', $user->id)
                    ->where('enrollments.status', 'active')
                    ->pluck('enrollments.user_id')
                    ->unique();

                // Also get students who have sent messages to this tutor
                $messageStudentIds = Message::where('receiver_id', $user->id)
                    ->pluck('sender_id')
                    ->unique();

                // Combine both sets of student IDs
                $allStudentIds = $studentIds->merge($messageStudentIds)->unique();

                $students = User::whereIn('id', $allStudentIds)->get();

                $conversations = $students->map(function ($student) use ($user) {
                    $lastMessage = Message::where(function ($q) use ($user, $student) {
                            $q->where('sender_id', $user->id)
                              ->where('receiver_id', $student->id);
                        })
                        ->orWhere(function ($q) use ($user, $student) {
                            $q->where('sender_id', $student->id)
                              ->where('receiver_id', $user->id);
                        })
                        ->latest()
                        ->first();

                    return [
                        'user_id' => $student->id,
                        'name' => $student->name,
                        'role' => $student->role,
                        'last_message' => $lastMessage?->message ?? 'No messages yet',
                        'last_message_time' => $lastMessage?->created_at ?? now(),
                        'unread_count' => Message::where('sender_id', $student->id)
                            ->where('receiver_id', $user->id)
                            ->where('is_read', false)
                            ->count(),
                    ];
                });

                // ✅ ADD ADMIN TO TUTOR'S CONVERSATIONS
                $adminUser = User::where('role', 'admin')->first();
                if ($adminUser) {
                    $adminLastMessage = Message::where(function ($q) use ($user, $adminUser) {
                            $q->where('sender_id', $user->id)
                              ->where('receiver_id', $adminUser->id);
                        })
                        ->orWhere(function ($q) use ($user, $adminUser) {
                            $q->where('sender_id', $adminUser->id)
                              ->where('receiver_id', $user->id);
                        })
                        ->latest()
                        ->first();

                    $adminConversation = [
                        'user_id' => $adminUser->id,
                        'name' => $adminUser->name,
                        'role' => $adminUser->role,
                        'last_message' => $adminLastMessage?->message ?? 'System Announcements',
                        'last_message_time' => $adminLastMessage?->created_at ?? now(),
                        'unread_count' => Message::where('sender_id', $adminUser->id)
                            ->where('receiver_id', $user->id)
                            ->where('is_read', false)
                            ->count(),
                    ];

                    // Add admin to conversations
                    $conversations->push($adminConversation);
                }

            } else {
                // For students: Get tutors from enrolled courses + existing message conversations
                $conversations = collect();

                // Get tutors from enrolled courses
                $tutorIds = DB::table('enrollments')
                    ->join('courses', 'enrollments.course_id', '=', 'courses.id')
                    ->join('tutorials', 'tutorials.course_id', '=', 'courses.id')
                    ->where('enrollments.user_id', $user->id)
                    ->where('enrollments.status', 'active')
                    ->pluck('tutorials.tutor_id')
                    ->unique();

                // Also get users who have sent messages to this student
                $messageSenderIds = Message::where('receiver_id', $user->id)
                    ->pluck('sender_id')
                    ->unique();

                // Also get users this student has sent messages to
                $messageReceiverIds = Message::where('sender_id', $user->id)
                    ->pluck('receiver_id')
                    ->unique();

                // Combine all user IDs
                $allUserIds = $tutorIds->merge($messageSenderIds)->merge($messageReceiverIds)->unique();

                $users = User::whereIn('id', $allUserIds)->get();

                $conversations = $users->map(function($other) use ($user) {
                    $lastMessage = Message::where(function ($q) use ($user, $other) {
                            $q->where('sender_id', $user->id)
                              ->where('receiver_id', $other->id);
                        })
                        ->orWhere(function ($q) use ($user, $other) {
                            $q->where('sender_id', $other->id)
                              ->where('receiver_id', $user->id);
                        })
                        ->latest()
                        ->first();

                    return [
                        'user_id' => $other->id,
                        'name' => $other->name,
                        'role' => $other->role,
                        'last_message' => $lastMessage?->message ?? 'No messages yet',
                        'last_message_time' => $lastMessage?->created_at ?? now(),
                        'unread_count' => Message::where('sender_id', $other->id)
                            ->where('receiver_id', $user->id)
                            ->where('is_read', false)
                            ->count(),
                    ];
                });
            }

            return response()->json([
                'success' => true,
                'conversations' => $conversations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages with a specific user
     */
    public function messages(Request $request, $userId)
{
    try {
        $currentUser = $request->user();
        
        // Validate the other user exists
        $otherUser = User::find($userId);
        if (!$otherUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // ✅ ONLY GET DIRECT MESSAGES BETWEEN USERS
        // ✅ REMOVE ANNOUNCEMENTS FROM HERE
        $messages = Message::where(function($query) use ($currentUser, $userId) {
                $query->where('sender_id', $currentUser->id)
                      ->where('receiver_id', $userId);
            })
            ->orWhere(function($query) use ($currentUser, $userId) {
                $query->where('sender_id', $userId)
                      ->where('receiver_id', $currentUser->id);
            })
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($message) use ($currentUser) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->name,
                    'receiver_id' => $message->receiver_id,
                    'receiver_name' => $message->receiver->name,
                    'is_own_message' => $message->sender_id === $currentUser->id,
                    'timestamp' => $message->created_at,
                    'is_read' => $message->is_read,
                    'is_announcement' => false // Always false for direct messages
                ];
            });

        // Mark messages as read
        Message::where('sender_id', $userId)
            ->where('receiver_id', $currentUser->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'other_user' => [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'role' => $otherUser->role
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch messages',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get announcements for current user
     */
    public function announcements(Request $request)
    {
        try {
            $currentUser = $request->user();
            
            $adminUser = User::where('role', 'admin')->first();
            $adminId = $adminUser ? $adminUser->id : null;
            $announcements = Message::where('receiver_id', $currentUser->id)
                ->where('sender_id', $adminId)
                ->where('message', 'LIKE', '%ANNOUNCEMENT%')
                ->with(['sender'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($message) use ($currentUser) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender_id' => $message->sender_id,
                        'sender_name' => $message->sender->name,
                        'is_own_message' => false,
                        'timestamp' => $message->created_at,
                        'is_read' => $message->is_read,
                        'is_announcement' => true
                    ];
                });

            return response()->json([
                'success' => true,
                'announcements' => $announcements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch announcements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message
     */
    public function send(Request $request)
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'receiver_id' => 'required|exists:users,id',
                'message' => 'required|string|max:1000'
            ]);

            $message = Message::create([
                'sender_id' => $user->id,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message,
                'is_read' => false
            ]);

            // Load relationships for response
            $message->load(['sender', 'receiver']);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'message_data' => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->name,
                    'receiver_id' => $message->receiver_id,
                    'timestamp' => $message->created_at,
                    'is_read' => $message->is_read
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            // Find the message
            $message = Message::find($id);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            // Check if user is the receiver of this message
            if ($message->receiver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to mark this message as read'
                ], 403);
            }

            // Update only if not already read
            if (!$message->is_read) {
                $message->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read',
                'message_id' => $message->id,
                'was_already_read' => $message->wasChanged('is_read') ? false : true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark message as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all messages from a specific user as read
     */
    public function markUserMessagesAsRead(Request $request, $userId)
    {
        try {
            $user = $request->user();
            
            // Validate the other user exists
            $otherUser = User::find($userId);
            if (!$otherUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Mark all unread messages from this user as read
            $updatedCount = Message::where('sender_id', $userId)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read',
                'updated_count' => $updatedCount,
                'from_user' => [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Typing indicator
     */
    public function typingIndicator(Request $request, $userId)
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'typing' => 'required|boolean'
            ]);

            // Validate that the target user exists
            $targetUser = User::find($userId);
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Don't allow typing to yourself
            if ($user->id == $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot send typing indicator to yourself'
                ], 400);
            }

            // Here you would typically broadcast this event via WebSocket/Pusher
            // For now, we'll just return success and log the event
            Log::info('Typing indicator', [
                'from_user_id' => $user->id,
                'to_user_id' => $userId,
                'typing' => $request->typing,
                'timestamp' => now()
            ]);

            $typingStatus = $request->typing ? 'started' : 'stopped';
            
            return response()->json([
                'success' => true,
                'message' => "Typing {$typingStatus}",
                'from_user' => [
                    'id' => $user->id,
                    'name' => $user->name
                ],
                'to_user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name
                ],
                'typing' => $request->typing,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update typing indicator',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread message count
     */
    public function unreadCount(Request $request)
    {
        try {
            $user = $request->user();
            
            $unreadCount = Message::where('receiver_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'unread_count' => $unreadCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug message list
     */
    public function debugList(Request $request)
    {
        try {
            $user = $request->user();
            
            $messages = Message::where('sender_id', $user->id)
                ->orWhere('receiver_id', $user->id)
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($message) use ($user) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'sender_id' => $message->sender_id,
                        'sender_name' => $message->sender->name,
                        'receiver_id' => $message->receiver_id,
                        'receiver_name' => $message->receiver->name,
                        'is_read' => $message->is_read,
                        'read_at' => $message->read_at,
                        'created_at' => $message->created_at,
                        'can_mark_read' => $message->receiver_id === $user->id && !$message->is_read
                    ];
                });

            return response()->json([
                'success' => true,
                'current_user_id' => $user->id,
                'messages' => $messages,
                'total_messages' => Message::count(),
                'user_messages' => Message::where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id)
                    ->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Debug failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}