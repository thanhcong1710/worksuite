@component('mail::message')
# @lang('email.hello')@if(!empty($notifiableName)){{ ' '.$notifiableName }}@endif!

@component('mail::text', ['text' => $content])

@endcomponent

@component('mail::button', ['url' => $url, 'themeColor' => $themeColor])
@lang('app.view') @lang('app.project')
@endcomponent

@lang('email.regards'),<br>
{{ config('app.name') }}
@endcomponent
