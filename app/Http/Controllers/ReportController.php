<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveReportRequest;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display a paginated list of all reports for admins.
     */
    public function index(Request $request): Response
    {
        $query = Report::query()
            ->with(['user:id,name,email', 'order:id,order_number'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('report_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $reports = $query->paginate(15)->through(fn (Report $report) => [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'category' => $report->category,
            'description' => $report->description,
            'status' => $report->status,
            'user' => [
                'id' => $report->user->id,
                'name' => $report->user->name,
                'email' => $report->user->email,
            ],
            'order_number' => $report->order?->order_number,
            'created_at' => $report->created_at?->toIso8601String(),
        ]);

        return Inertia::render('reports/index', [
            'reports' => $reports,
            'filters' => $request->only(['status', 'search', 'category']),
            'categories' => Report::getCategories(),
            'statuses' => [
                ['value' => Report::STATUS_PENDING, 'label' => 'Pending'],
                ['value' => Report::STATUS_IN_PROGRESS, 'label' => 'In Progress'],
                ['value' => Report::STATUS_RESOLVED, 'label' => 'Resolved'],
                ['value' => Report::STATUS_CANCELLED, 'label' => 'Cancelled'],
            ],
        ]);
    }

    /**
     * Display a single report detail for admin review.
     */
    public function show(Report $report): Response
    {
        $report->load(['user:id,name,email,phone', 'order:id,order_number', 'attachments', 'resolver:id,name']);

        return Inertia::render('reports/show', [
            'report' => [
                'id' => $report->id,
                'report_number' => $report->report_number,
                'category' => $report->category,
                'description' => $report->description,
                'status' => $report->status,
                'user' => [
                    'id' => $report->user->id,
                    'name' => $report->user->name,
                    'email' => $report->user->email,
                    'phone' => $report->user->phone ?? null,
                ],
                'order' => $report->order ? [
                    'id' => $report->order->id,
                    'order_number' => $report->order->order_number,
                ] : null,
                'attachments' => $report->attachments->map(fn ($att) => [
                    'id' => $att->id,
                    'file_name' => $att->file_name,
                    'file_size' => $att->file_size,
                    'mime_type' => $att->mime_type,
                    'url' => $att->url,
                ]),
                'resolver' => $report->resolver ? [
                    'id' => $report->resolver->id,
                    'name' => $report->resolver->name,
                ] : null,
                'resolution_notes' => $report->resolution_notes,
                'cancellation_reason' => $report->cancellation_reason,
                'resolved_at' => $report->resolved_at?->toIso8601String(),
                'created_at' => $report->created_at?->toIso8601String(),
                'updated_at' => $report->updated_at?->toIso8601String(),
                'can_be_cancelled' => $report->canBeCancelled(),
                'is_pending' => $report->isPending(),
                'is_in_progress' => $report->isInProgress(),
            ],
        ]);
    }

    /**
     * Mark a report as in progress.
     */
    public function updateStatus(Request $request, Report $report): RedirectResponse
    {
        if (! $report->isPending()) {
            return back()->with('error', 'Only pending reports can be marked as in progress.');
        }

        $report->markAsInProgress();

        return back()->with('success', 'Report marked as in progress.');
    }

    /**
     * Resolve a report with resolution notes.
     */
    public function resolve(ResolveReportRequest $request, Report $report): RedirectResponse
    {
        if ($report->isResolved() || $report->isCancelled()) {
            return back()->with('error', 'This report cannot be resolved.');
        }

        $report->markAsResolved($request->user()->id, $request->input('resolution_notes'));

        return back()->with('success', 'Report resolved successfully.');
    }
}
