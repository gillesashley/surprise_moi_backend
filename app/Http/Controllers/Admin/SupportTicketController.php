<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Sms\SmsProviderInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignSupportTicketRequest;
use App\Http\Requests\Admin\SendSupportMessageRequest;
use App\Http\Requests\Admin\StoreSupportInteractionRequest;
use App\Http\Requests\Admin\StoreSupportTicketRequest;
use App\Http\Requests\Admin\UpdateSupportTicketStatusRequest;
use App\Models\SupportInteraction;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    /**
     * Display a paginated list of support tickets for customer-care reps.
     */
    public function index(Request $request): Response
    {
        $query = SupportTicket::query()
            ->with([
                'user:id,name,email,phone',
                'assignee:id,name',
                'creator:id,name',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $tickets = $query->paginate(15)->through(fn (SupportTicket $ticket): array => [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'contact_name' => $ticket->contact_name,
            'contact_phone' => $ticket->contact_phone,
            'user' => $ticket->user ? [
                'id' => $ticket->user->id,
                'name' => $ticket->user->name,
                'email' => $ticket->user->email,
            ] : null,
            'assignee' => $ticket->assignee ? [
                'id' => $ticket->assignee->id,
                'name' => $ticket->assignee->name,
            ] : null,
            'created_at' => $ticket->created_at?->toIso8601String(),
        ]);

        return Inertia::render('customer-support/index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'priority', 'category', 'search']),
            'categories' => $this->categoryOptions(),
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
        ]);
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $ticket = SupportTicket::create([
            ...$request->validated(),
            'priority' => $request->input('priority', SupportTicket::PRIORITY_NORMAL),
            'status' => SupportTicket::STATUS_OPEN,
            'created_by' => $request->user()->id,
            'assigned_to' => $request->input('assigned_to', $request->user()->id),
        ]);

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'Ticket created.');
    }

    /**
     * Display a single ticket with its merged timeline (interactions + messages).
     */
    public function show(SupportTicket $ticket): Response
    {
        $ticket->load([
            'user:id,name,email,phone',
            'assignee:id,name',
            'creator:id,name',
            'closer:id,name',
            'order:id,order_number',
            'interactions' => fn ($q) => $q->with('creator:id,name')->orderByDesc('occurred_at'),
            'messages' => fn ($q) => $q->with('sender:id,name')->latest(),
        ]);

        return Inertia::render('customer-support/show', [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'contact_name' => $ticket->contact_name,
                'contact_phone' => $ticket->contact_phone,
                'contact_email' => $ticket->contact_email,
                'user' => $ticket->user ? [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                    'email' => $ticket->user->email,
                    'phone' => $ticket->user->phone ?? null,
                ] : null,
                'assignee' => $ticket->assignee ? [
                    'id' => $ticket->assignee->id,
                    'name' => $ticket->assignee->name,
                ] : null,
                'creator' => $ticket->creator ? [
                    'id' => $ticket->creator->id,
                    'name' => $ticket->creator->name,
                ] : null,
                'closer' => $ticket->closer ? [
                    'id' => $ticket->closer->id,
                    'name' => $ticket->closer->name,
                ] : null,
                'order' => $ticket->order ? [
                    'id' => $ticket->order->id,
                    'order_number' => $ticket->order->order_number,
                ] : null,
                'closure_note' => $ticket->closure_note,
                'closed_at' => $ticket->closed_at?->toIso8601String(),
                'created_at' => $ticket->created_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
            ],
            'timeline' => $this->buildTimeline($ticket),
            'assignees' => User::query()
                ->whereIn('role', ['admin', 'super_admin'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
                ->all(),
            'templates' => config('support_templates', []),
            'channels' => SupportInteraction::CHANNELS,
            'statuses' => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
            'preferredPhone' => $ticket->user?->phone ?? $ticket->contact_phone,
        ]);
    }

    /**
     * Log a rep-initiated interaction (call, in-person, etc.) against a ticket.
     */
    public function storeInteraction(StoreSupportInteractionRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->interactions()->create([
            ...$request->validated(),
            'occurred_at' => $request->input('occurred_at') ?? now(),
            'created_by' => $request->user()->id,
        ]);

        if ($ticket->status === SupportTicket::STATUS_OPEN) {
            $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        }

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'Interaction logged.');
    }

    /**
     * Send an SMS through the configured provider and persist a SupportMessage row.
     */
    public function sendMessage(
        SendSupportMessageRequest $request,
        SupportTicket $ticket,
        SmsProviderInterface $sms,
    ): RedirectResponse {
        $recipient = $request->input('to_phone') ?: ($ticket->user?->phone ?: $ticket->contact_phone);

        if (! $recipient) {
            return back()->with('error', 'No phone number available for this ticket.');
        }

        $message = $ticket->messages()->create([
            'to_user_id' => $ticket->user_id,
            'to_phone' => $recipient,
            'body' => $request->input('body'),
            'template_key' => $request->input('template_key'),
            'status' => SupportMessage::STATUS_QUEUED,
            'sent_by' => $request->user()->id,
        ]);

        $result = $sms->send($recipient, $request->input('body'));

        if (($result['success'] ?? false) === true) {
            $message->update([
                'status' => SupportMessage::STATUS_SENT,
                'sent_at' => now(),
            ]);
            $flash = ['success' => 'SMS sent.'];
        } else {
            $message->update([
                'status' => SupportMessage::STATUS_FAILED,
                'failed_reason' => substr((string) ($result['message'] ?? 'Unknown error'), 0, 500),
            ]);
            $flash = ['error' => 'SMS failed: '.($result['message'] ?? 'Unknown error')];
        }

        if ($ticket->status === SupportTicket::STATUS_OPEN) {
            $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        }

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with($flash);
    }

    /**
     * Change the ticket status; closing requires a closure_note.
     */
    public function updateStatus(UpdateSupportTicketStatusRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $newStatus = $request->input('status');

        $payload = ['status' => $newStatus];

        if ($newStatus === SupportTicket::STATUS_CLOSED) {
            $payload['closure_note'] = $request->input('closure_note');
            $payload['closed_at'] = now();
            $payload['closed_by'] = $request->user()->id;
        } else {
            $payload['closure_note'] = null;
            $payload['closed_at'] = null;
            $payload['closed_by'] = null;
        }

        $ticket->update($payload);

        return back()->with('success', 'Ticket status updated.');
    }

    /**
     * Reassign a ticket to another admin/super_admin.
     */
    public function assign(AssignSupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->update(['assigned_to' => $request->integer('assigned_to')]);

        return back()->with('success', 'Ticket reassigned.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTimeline(SupportTicket $ticket): array
    {
        $interactions = $ticket->interactions->map(fn (SupportInteraction $i): array => [
            'type' => 'interaction',
            'id' => $i->id,
            'channel' => $i->channel,
            'direction' => $i->direction,
            'summary' => $i->summary,
            'occurred_at' => $i->occurred_at?->toIso8601String(),
            'follow_up_at' => $i->follow_up_at?->toDateString(),
            'creator' => $i->creator ? ['id' => $i->creator->id, 'name' => $i->creator->name] : null,
            'sort_at' => $i->occurred_at?->toIso8601String() ?? $i->created_at?->toIso8601String(),
        ]);

        $messages = $ticket->messages->map(fn (SupportMessage $m): array => [
            'type' => 'message',
            'id' => $m->id,
            'body' => $m->body,
            'template_key' => $m->template_key,
            'to_phone' => $m->to_phone,
            'status' => $m->status,
            'failed_reason' => $m->failed_reason,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'sender' => $m->sender ? ['id' => $m->sender->id, 'name' => $m->sender->name] : null,
            'sort_at' => $m->sent_at?->toIso8601String() ?? $m->created_at?->toIso8601String(),
        ]);

        return $interactions
            ->concat($messages)
            ->sortByDesc('sort_at')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => SupportTicket::STATUS_OPEN, 'label' => 'Open'],
            ['value' => SupportTicket::STATUS_IN_PROGRESS, 'label' => 'In Progress'],
            ['value' => SupportTicket::STATUS_CLOSED, 'label' => 'Closed'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function priorityOptions(): array
    {
        return [
            ['value' => SupportTicket::PRIORITY_LOW, 'label' => 'Low'],
            ['value' => SupportTicket::PRIORITY_NORMAL, 'label' => 'Normal'],
            ['value' => SupportTicket::PRIORITY_HIGH, 'label' => 'High'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return array_map(
            fn (string $value): array => ['value' => $value, 'label' => ucwords(str_replace('_', ' ', $value))],
            SupportTicket::CATEGORIES,
        );
    }
}
