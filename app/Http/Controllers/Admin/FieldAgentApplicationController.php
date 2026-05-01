<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FieldAgentApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectFieldAgentApplicationRequest;
use App\Models\FieldAgentApplication;
use App\Services\FieldAgentApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FieldAgentApplicationController extends Controller
{
    public function __construct(private FieldAgentApprovalService $service) {}

    public function index(Request $request): Response
    {
        $query = FieldAgentApplication::query()
            ->with(['region:id,name', 'city:id,name', 'reviewer:id,name']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($regionId = $request->integer('region_id')) {
            $query->where('region_id', $regionId);
        }

        $type = $request->string('type')->toString();
        if (in_array($type, ['individual', 'team'], true)) {
            $query->where('is_team', $type === 'team');
        }

        $applications = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/field-agent-applications/index', [
            'applications' => $applications,
            'filters' => $request->only(['status', 'region_id', 'type']),
            'statuses' => collect(FieldAgentApplicationStatus::cases())
                ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(FieldAgentApplication $fieldAgentApplication): Response
    {
        $fieldAgentApplication->load(['region:id,name', 'city:id,name', 'reviewer:id,name', 'approvedUser:id,name,email']);

        $disk = Storage::disk(config('filesystems.default'));

        return Inertia::render('admin/field-agent-applications/show', [
            'application' => array_merge($fieldAgentApplication->toArray(), [
                'ghana_card_image_url' => $disk->url($fieldAgentApplication->ghana_card_image_path),
                'ghana_card_back_image_url' => $fieldAgentApplication->ghana_card_back_image_path
                    ? $disk->url($fieldAgentApplication->ghana_card_back_image_path)
                    : null,
                'selfie_url' => $disk->url($fieldAgentApplication->selfie_path),
            ]),
        ]);
    }

    public function approve(Request $request, FieldAgentApplication $fieldAgentApplication): RedirectResponse
    {
        if (! $fieldAgentApplication->canBeReviewed()) {
            throw ValidationException::withMessages([
                'status' => 'This application has already been reviewed.',
            ]);
        }

        try {
            $this->service->approve($fieldAgentApplication, $request->user());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        app(\App\Services\AuditService::class)->record(
            'field_agent_application.approved',
            $fieldAgentApplication,
            $request->user(),
            retentionClass: 'critical'
        );

        return redirect()->route('admin.field-agent-applications.show', $fieldAgentApplication)
            ->with('success', 'Field agent approved.');
    }

    public function reject(RejectFieldAgentApplicationRequest $request, FieldAgentApplication $fieldAgentApplication): RedirectResponse
    {
        if (! $fieldAgentApplication->canBeReviewed()) {
            throw ValidationException::withMessages([
                'status' => 'This application has already been reviewed.',
            ]);
        }

        try {
            $this->service->reject($fieldAgentApplication, $request->user(), $request->string('rejection_reason')->toString());
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        app(\App\Services\AuditService::class)->record(
            'field_agent_application.rejected',
            $fieldAgentApplication,
            $request->user(),
            extra: ['reason' => $request->string('rejection_reason')->toString()],
            retentionClass: 'critical'
        );

        return redirect()->route('admin.field-agent-applications.show', $fieldAgentApplication)
            ->with('success', 'Application rejected.');
    }
}
