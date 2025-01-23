@component('mail::message')
<h1>Verify your email address</h1>
    <p>Verification Code</p>


@component('mail::panel')
{{ $code }}
@endcomponent

<p>This code is valid for one hour from the time this message was sent</p>
@endcomponent
