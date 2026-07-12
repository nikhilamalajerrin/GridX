<?php

namespace App\Http\Controllers;

use App\Models\GridxAgentSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * One row per AI agent run — lets a dispatcher see what the agent decided
 * and why (its reasoning, which quotes it created) instead of only seeing
 * the resulting quote with no context. Sessions are opened by the agent at
 * the start of handling a message and closed with a summary at the end.
 */
class AgentSessionController extends Controller
{
    public function index(Request $request)
    {
        $companyUuid = Auth::user()?->company_uuid ?? session('company');

        $sessions = GridxAgentSession::where('company_uuid', $companyUuid)
            ->withCount('quotes as decision_count')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'mode'    => 'nullable|string',
            'channel' => 'nullable|string',
            'sender'  => 'nullable|string',
        ]);

        $companyUuid = Auth::user()?->company_uuid ?? session('company');
        if (!$companyUuid) {
            return response()->json(['error' => 'No company context for this session.'], 422);
        }

        $session = GridxAgentSession::create([
            'uuid'         => (string) Str::uuid(),
            'company_uuid' => $companyUuid,
            'mode'         => $data['mode'] ?? 'suggestions',
            'channel'      => $data['channel'] ?? null,
            'sender'       => $data['sender'] ?? null,
            'status'       => 'running',
            'started_at'   => now(),
        ]);

        return response()->json(['session' => $session], 201);
    }

    public function complete(Request $request, string $id)
    {
        $data = $request->validate([
            'status'  => 'required|string|in:completed,failed',
            'summary' => 'nullable|string',
        ]);

        $session = GridxAgentSession::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();
        $session->update([
            'status'       => $data['status'],
            'summary'      => $data['summary'] ?? null,
            'completed_at' => now(),
        ]);

        return response()->json(['session' => $session]);
    }

    /**
     * Quotes created under this session, with their pending/approved/
     * rejected status — the "decisions" the Pending Decisions panel reviews.
     */
    public function decisions(string $id)
    {
        $session = GridxAgentSession::where('public_id', $id)->orWhere('uuid', $id)->firstOrFail();

        return response()->json(['decisions' => $session->quotes()->orderByDesc('created_at')->get()]);
    }
}
