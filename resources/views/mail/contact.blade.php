@component('mail::message')
# Nieuw bericht via het contactformulier

**Van:** {{ $senderName }} &lt;{{ $senderEmail }}&gt;
**Onderwerp:** {{ $senderSubject }}

@component('mail::panel')
{{ $body }}
@endcomponent

Je kunt rechtstreeks op deze mail antwoorden; het antwoord gaat naar {{ $senderEmail }}.

@endcomponent
