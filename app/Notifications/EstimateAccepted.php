<?php

namespace App\Notifications;

use App\Models\Estimate;
use Illuminate\Bus\Queueable;
use App\Models\EmailNotificationSetting;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class EstimateAccepted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    private $estimate;
    private $company;
    private $emailSetting;

    public function __construct(Estimate $estimate)
    {
        $this->estimate = $estimate;
        $this->company = $this->estimate->company;
        $this->emailSetting = EmailNotificationSetting::where('company_id', $this->company->id)->where('slug', 'estimate-notification')->first();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array
     */
    public function via()
    {
        $via = [];

        if ($this->emailSetting->send_slack == 'yes' && $this->company->slackSetting->status == 'active') {
            array_push($via, 'slack');
        }

        return $via;
    }

    public function toSlack($notifiable)
    {
        $slack = $notifiable->company->slackSetting;

        if (count($notifiable->employee) > 0 && (!is_null($notifiable->employee[0]->slack_username) && ($notifiable->employee[0]->slack_username != ''))) {
            return (new SlackMessage())
                ->from(config('app.name'))
                ->to('@' . $notifiable->employee[0]->slack_username)
                ->image($slack->slack_logo_url)
                ->content(__('email.hello')  . ' ' .  $notifiable->name . $this->estimate->estimate_number .' '. __('email.estimateAccepted.subject'));
        }

        return (new SlackMessage())
            ->from(config('app.name'))
            ->image($slack->slack_logo_url)
            ->content(__('email.hello')  . ' ' .  $notifiable->name .' '. $this->estimate->estimate_number .' '. __('email.estimateAccepted.subject'));

    }

}
