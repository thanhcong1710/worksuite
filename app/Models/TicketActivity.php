<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends BaseModel
{

    protected $with = ['user', 'assignedTo', 'channel', 'group', 'ticketType'];
    protected $appends = ['details'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TicketChannel::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TicketGroup::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function details(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match($this->type) {
                    'create' => __('modules.tickets.activity.create'),
                    'reply' => __('modules.tickets.activity.reply', ['userName' => $this->user->name]),
                    'group' => __('modules.tickets.activity.group', ['groupName' => $this->group?->group_name ?: '--']),
                    'assign' => __('modules.tickets.activity.assign', ['userName' => $this->assignedTo?->name ?: '--']),
                    'priority' => __('modules.tickets.activity.priority', ['priority' => __('app.'.$this->priority)]),
                    'type' => __('modules.tickets.activity.type', ['type' => $this->ticketType?->type ?: '--']),
                    'channel' => __('modules.tickets.activity.channel', ['channel' => $this->channel?->channel_name ?: '--']),
                    'status' => __('modules.tickets.activity.status', ['status' => __('app.'.$this->status)]),
                    default => '',
                };
            }
        );
    }

}
