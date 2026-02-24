<?php

namespace App\Http\Controllers;

use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TargetController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Target::class);

        $targets = Target::with(['user', 'assignedBy'])
            ->latest()
            ->paginate(15);

        return Inertia::render('targets/index', [
            'targets' => $targets,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Target::class);

        $eligibleUsers = User::whereIn('role', ['field_agent', 'marketer'])->get();

        return Inertia::render('targets/create', [
            'users' => $eligibleUsers,
            'targetTypes' => [
                Target::TYPE_VENDOR_SIGNUPS => 'Vendor Signups',
                Target::TYPE_REVENUE_GENERATED => 'Revenue Generated',
            ],
            'periodTypes' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Target::class);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'target_type' => 'required|in:'.Target::TYPE_VENDOR_SIGNUPS.','.Target::TYPE_REVENUE_GENERATED,
            'target_value' => 'required|numeric|min:1',
            'bonus_amount' => 'required|numeric|min:0',
            'overachievement_rate' => 'nullable|numeric|min:0|max:100',
            'period_type' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($validated['user_id']);

        Target::create([
            'user_id' => $user->id,
            'assigned_by' => Auth::id(),
            'user_role' => $user->role,
            'target_type' => $validated['target_type'],
            'target_value' => $validated['target_value'],
            'current_value' => 0,
            'bonus_amount' => $validated['bonus_amount'],
            'overachievement_rate' => $validated['overachievement_rate'] ?? 0,
            'period_type' => $validated['period_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => Target::STATUS_ACTIVE,
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('targets.index')
            ->with('success', 'Target assigned successfully.');
    }

    public function show(Target $target)
    {
        $this->authorize('view', $target);

        $target->load(['user', 'assignedBy']);

        return Inertia::render('targets/show', [
            'target' => $target,
        ]);
    }

    public function edit(Target $target)
    {
        $this->authorize('update', $target);

        $eligibleUsers = User::whereIn('role', ['field_agent', 'marketer'])->get();

        return Inertia::render('targets/edit', [
            'target' => $target->load('user'),
            'users' => $eligibleUsers,
            'targetTypes' => [
                Target::TYPE_VENDOR_SIGNUPS => 'Vendor Signups',
                Target::TYPE_REVENUE_GENERATED => 'Revenue Generated',
            ],
            'periodTypes' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'],
        ]);
    }

    public function update(Request $request, Target $target)
    {
        $this->authorize('update', $target);

        $validated = $request->validate([
            'target_value' => 'required|numeric|min:1',
            'bonus_amount' => 'required|numeric|min:0',
            'overachievement_rate' => 'nullable|numeric|min:0|max:100',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string|max:500',
            'status' => 'required|in:active,completed,expired,cancelled',
        ]);

        $target->update($validated);

        return redirect()->route('targets.index')
            ->with('success', 'Target updated successfully.');
    }

    public function destroy(Target $target)
    {
        $this->authorize('delete', $target);

        $target->delete();

        return redirect()->back()
            ->with('success', 'Target deleted successfully.');
    }
}
