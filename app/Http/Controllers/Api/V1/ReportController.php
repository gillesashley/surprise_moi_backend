<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\ReportAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    /**
     * Get a paginated list of reports for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Report::query()
            ->with(['attachments'])
            ->where('user_id', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reports = $query->latest()->paginate($request->input('per_page', 15));

        return ReportResource::collection($reports);
    }

    /**
     * Create a new report with optional file attachments.
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = Report::create([
            'user_id' => $request->user()->id,
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'order_id' => $request->input('order_id'),
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('reports/'.$report->id);

                ReportAttachment::create([
                    'report_id' => $report->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType() ?? 'image/jpeg',
                ]);
            }
        }

        $report->load(['attachments', 'order']);

        return response()->json([
            'message' => 'Report submitted successfully.',
            'report' => new ReportResource($report),
        ], 201);
    }

    /**
     * Get a single report for the authenticated user.
     */
    public function show(Request $request, Report $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        $report->load(['attachments', 'order', 'resolver']);

        return response()->json(['report' => new ReportResource($report)]);
    }

    /**
     * Get the list of available report categories.
     */
    public function categories(): JsonResponse
    {
        return response()->json(['categories' => Report::getCategories()]);
    }

    /**
     * Cancel a pending report.
     */
    public function cancel(CancelReportRequest $request, Report $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        if (! $report->canBeCancelled()) {
            return response()->json([
                'message' => 'This report cannot be cancelled. Only pending reports can be cancelled.',
            ], 422);
        }

        $report->markAsCancelled($request->input('reason'));

        return response()->json([
            'message' => 'Report cancelled successfully.',
            'report' => new ReportResource($report),
        ]);
    }
}
