<?php

namespace App\Observers;

use App\Events\LeadEvent;
use App\Models\Lead;
use App\Notifications\LeadAgentAssigned;
use App\Models\UniversalSearch;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class LeadObserver
{

    public function saving(Lead $lead)
    {
        if (!isRunningInConsoleOrSeeding()) {
            $userID = (!is_null(user())) ? user()->id : null;
            $lead->last_updated_by = $userID;
        }
    }

    public function creating(Lead $lead)
    {
        $lead->hash = md5(microtime());

        if (!isRunningInConsoleOrSeeding()) {
            $userID = (!is_null(user())) ? user()->id : null;
            $lead->added_by = $userID;
        }

        if (company()) {
            $lead->company_id = company()->id;
        }
    }

    public function updated(Lead $lead)
    {
        if (!isRunningInConsoleOrSeeding()) {
            if ($lead->isDirty('agent_id')) {
                event(new LeadEvent($lead, $lead->leadAgent, 'LeadAgentAssigned'));
            }
        }
    }

    public function created(Lead $lead)
    {
        // Save lead note
        if (!empty($lead->note)) {
            $lead->note()->create([
                'lead_id' => $lead->id,
                'title' => 'Note',
                'details' => $lead->note
            ]);
        }

        if (!isRunningInConsoleOrSeeding()) {
            if (request('agent_id') != '') {
                event(new LeadEvent($lead, $lead->leadAgent, 'LeadAgentAssigned'));
            }
            else {
                Notification::send(User::allAdmins($lead->company->id), new LeadAgentAssigned($lead));
            }
        }
    }

    public function deleting(Lead $lead)
    {
        $notifyData = ['App\Notifications\LeadAgentAssigned'];
        \App\Models\Notification::deleteNotification($notifyData, $lead->id);

    }

    public function deleted(Lead $lead)
    {
        UniversalSearch::where('searchable_id', $lead->id)->where('module_type', 'lead')->delete();
    }

}
