<?php

namespace App\Notifications;

use App\Models\EmailNotificationSetting;
use App\Models\Lead;

class LeadAgentAssigned extends BaseNotification
{

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    private $lead;
    private $emailSetting;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
        $this->company = $this->lead->company;
        $this->emailSetting = EmailNotificationSetting::where('company_id', $this->company->id)->where('slug', 'lead-notification')->first();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $via = array('database');

        if ($this->emailSetting->send_email == 'yes' && $notifiable->email_notifications && $notifiable->email != '') {
            array_push($via, 'mail');
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $build = parent::build();
        $url = route('leads.show', $this->lead->id);
        $url = getDomainSpecificUrl($url, $this->company);

        $salutation = ($this->lead->salutation ? $this->lead->salutation->label() : '') .' ';
        $leadEmail = __('modules.lead.clientEmail') . ': ';
        $clientEmail = !is_null($this->lead->client_email) ? $leadEmail : '';
        $content = __('email.leadAgent.subject') . '<br>' . __('modules.lead.clientName') . ': ' . $salutation . $this->lead->client_name . '<br>' . $clientEmail . $this->lead->client_email;

        return $build
            ->subject(__('email.leadAgent.subject') . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('email.leadAgent.action'),
                'notifiableName' => $notifiable->name
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    //phpcs:ignore
    public function toArray($notifiable)
    {
        return [
            'id' => $this->lead->id,
            'name' => $this->lead->client_name,
            'agent_id' => $notifiable->id,
            'added_by' => $this->lead->added_by
        ];
    }

}
