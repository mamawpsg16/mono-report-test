<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="padding:32px 36px;">
                            <h1 style="margin:0 0 8px; font-size:20px; color:#111827;">{{ config('app.name') }}</h1>
                            <p style="margin:0 0 20px; font-size:15px; color:#374151; line-height:1.6;">
                                Hi {{ $user->name }}, an account has been created for you. Set your
                                password to finish signing in.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:8px; background:#1d4ed8;">
                                        <a href="{{ $inviteUrl }}" target="_blank"
                                           style="display:inline-block; padding:12px 24px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                                            Set your password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px; font-size:13px; color:#6b7280; line-height:1.6;">
                                This link expires in 48 hours. If you weren't expecting this invitation,
                                you can safely ignore this email.
                            </p>
                            <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.6; word-break:break-all;">
                                If the button doesn't work, copy and paste this link into your browser:<br>
                                {{ $inviteUrl }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
