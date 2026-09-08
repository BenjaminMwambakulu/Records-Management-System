<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the CSIT Society</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f1fb; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f1fb; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #dedbe6;">
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px; font-size:22px; color:#110b79; font-weight:700;">
                                Welcome to the CSIT Society
                            </h1>
                            <p style="margin:0 0 20px; font-size:14px; color:#686579;">
                                Hi {{ $name }},
                            </p>
                            <p style="margin:0 0 20px; font-size:14px; color:#1e1e2a; line-height:1.6;">
                                Your account has been created. Please sign in using your email
                                (<strong>{{ $email }}</strong>) with the temporary password below:
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eae7f0; border-radius:8px; margin-bottom:20px;">
                                <tr>
                                    <td align="center" style="padding:16px; font-size:18px; font-weight:700; letter-spacing:1px; color:#1e1e2a;">
                                        {{ $temporaryPassword }}
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 20px; font-size:14px; color:#1e1e2a; line-height:1.6;">
                                Sign in at <a href="{{ $appUrl }}" style="color:#110b79;">{{ $appUrl }}</a>.
                            </p>
                            <p style="margin:0; font-size:13px; color:#686579; line-height:1.6;">
                                For security, please change your password after your first sign in.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background-color:#f8f7fb; border-top:1px solid #dedbe6;">
                            <p style="margin:0; font-size:12px; color:#686579;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>