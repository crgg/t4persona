@component('mail::message')
# New User Registration T4EVER

**Name:** {{ $user->name ?? '-' }}
**Email:** {{ $user->email ?? '-' }}
**Registered at:** {{ optional($user->created_at)->toDateTimeString() }}

## Client Meta
- IP: **{{ $ip }}**
- User-Agent:
`{{ $userAgent }}`

## Geo-IP
- City: {{ $geo['city'] ?? '-' }}
- Region: {{ $geo['region'] ?? '-' }}
- Country: {{ $geo['country'] ?? '-' }}
- Postal: {{ $geo['postal'] ?? '-' }}
- Timezone: {{ $geo['timezone'] ?? '-' }}
- Coords: {{ $geo['latitude'] ?? '-' }}, {{ $geo['longitude'] ?? '-' }}
- Org/ASN: {{ $geo['org'] ?? '-' }} / {{ $geo['asn'] ?? '-' }}

@endcomponent
