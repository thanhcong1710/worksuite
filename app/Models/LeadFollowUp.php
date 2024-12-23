<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\LeadFollowUp
 *
 * @property int $id
 * @property int $lead_id
 * @property string|null $remark
 * @property \Illuminate\Support\Carbon|null $next_follow_up_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $added_by
 * @property int|null $last_updated_by
 * @property-read mixed $icon
 * @property-read \App\Models\Lead $lead
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp query()
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereAddedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereLastUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereLeadId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereNextFollowUpDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereUpdatedAt($value)
 * @property string|null $event_id
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereEventId($value)
 * @property string|null $send_reminder
 * @property string|null $remind_time
 * @property string|null $remind_type
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereRemindTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereRemindType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereSendReminder($value)
 * @property-read \App\Models\User|null $addedBy
 * @property string|null $status
 * @method static \Illuminate\Database\Eloquent\Builder|LeadFollowUp whereStatus($value)
 * @mixin \Eloquent
 */
class LeadFollowUp extends BaseModel
{

    protected $table = 'lead_follow_up';
    protected $casts = [
        'next_follow_up_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

}
