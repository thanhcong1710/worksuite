@component('mail::message')
# @lang('email.hello')@if(!empty($notifiableName)){{ ' '.$notifiableName }}@endif!

@lang('email.newTask.subject')

# @lang('app.task') @lang('app.details')

@component('mail::text', ['text' => $content])

@endcomponent

@component('mail::button', ['url' => $url, 'themeColor' => $themeColor])
@lang('app.view') @lang('app.task')
@endcomponent

@lang('email.regards'),<br>
{{ config('app.name') }}
@endcomponent
