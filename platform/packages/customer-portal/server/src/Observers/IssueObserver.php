<?php

namespace GridX\CustomerPortal\Observers;

use GridX\CustomerPortal\Notifications\SupportTicketCreated;
use GridX\CustomerPortal\Services\PortalSupportService;
use GridX\FleetOps\Models\Issue;
use Illuminate\Support\Facades\Notification;

class IssueObserver
{
    public function created(Issue $issue): void
    {
        /** @var PortalSupportService $supportService */
        $supportService = app(PortalSupportService::class);

        if (!$supportService->isCustomerPortalIssue($issue)) {
            return;
        }

        $recipients = $supportService->operatorUsersForIssue($issue, $issue->reported_by_uuid);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SupportTicketCreated($issue->loadMissing('reporter')));
    }
}
