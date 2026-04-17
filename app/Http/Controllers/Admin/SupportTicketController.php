<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;

class SupportTicketController extends Controller
{
    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        $ticket = SupportTicket::create([
            ...$request->validated(),
            'priority' => $request->input('priority', SupportTicket::PRIORITY_NORMAL),
            'status' => SupportTicket::STATUS_OPEN,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('dashboard.customer-support.show', $ticket)
            ->with('success', 'Ticket created.');
    }
}
