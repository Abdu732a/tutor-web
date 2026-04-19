<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;
    public $student;
    public $course;

    public function __construct($payment, $student, $course)
    {
        $this->payment = $payment;
        $this->student = $student;
        $this->course = $course;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '💳 Payment Receipt - Academic Tutorial System',
            from: config('mail.from.address'),
            replyTo: config('mail.from.address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-receipt',
            with: [
                'student_name' => $this->student->user->name,
                'course_name' => $this->course->title,
                'amount' => $this->payment->amount,
                'transaction_id' => $this->payment->transaction_id ?? $this->payment->transaction_reference ?? 'N/A',
                'payment_date' => $this->payment->created_at ? $this->payment->created_at->format('M d, Y \a\t g:i A') : now()->format('M d, Y \a\t g:i A'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}