<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Verify your email</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:{{ $colors['bg'] }};font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:{{ $colors['bg'] }};padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:{{ $colors['card'] }};border:1px solid {{ $colors['border'] }};border-radius:12px;overflow:hidden;">
          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(90deg, {{ $colors['primary'] }}, {{ $colors['primaryD'] }});padding:20px 24px;text-align:left;">
              <table role="presentation" width="100%">
                <tr>
                  <td align="left" style="vertical-align:middle;">
                    @if(!empty($logoUrl))
                      <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" style="height:40px;display:block;">
                    @else
                      <div style="color:#FFFFFF;font-weight:bold;font-size:18px;letter-spacing:0.4px;">
                        {{ config('app.name') }}
                      </div>
                    @endif
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:28px 28px 8px 28px;color:{{ $colors['text'] }};">
              <h1 style="margin:0 0 10px 0;font-size:22px;line-height:28px;color:{{ $colors['text'] }};">
                Verify your email
              </h1>
              <p style="margin:0 0 16px 0;font-size:14px;line-height:22px;color:{{ $colors['muted'] }};">
                Hi {{ $user->name }}, please confirm your email address to activate your account. This secure link expires in 60 minutes.
              </p>
              <!-- CTA Button -->
              <table role="presentation" cellspacing="0" cellpadding="0" style="margin:18px 0 8px 0;">
                <tr>
                  <td align="left">
                    <a href="{{ $url }}" style="display:inline-block;padding:12px 18px;background:{{ $colors['primary'] }};color:#FFFFFF;text-decoration:none;border-radius:8px;font-size:14px;letter-spacing:.3px;">
                      Verify Email
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:18px 0 0 0;font-size:12px;line-height:19px;color:{{ $colors['muted'] }};">
                If you didn't create this account, you can safely ignore this message.<br>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:18px 28px 26px 28px;border-top:1px solid {{ $colors['border'] }};text-align:left;">
              <p style="margin:0;font-size:12px;line-height:18px;color:{{ $colors['muted'] }};">
                © {{ date('Y') }} {{ config('app.name') }} — All rights reserved.
              </p>
            </td>
          </tr>
        </table>

        <!-- Plain link fallback -->
        <p style="max-width:640px;margin:12px auto 0 auto;font-size:12px;line-height:18px;color:{{ $colors['muted'] }};padding:0 8px;">
          Trouble with the button? Copy and paste this URL in your browser:<br>
          <span style="word-break:break-all;">{{ $url }}</span>
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
