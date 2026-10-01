<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <style>
        /* General styling */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .wrapper {
            width: 100%;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .content {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #ffffff;
            padding: 20px 0;
            text-align: center;
        }
        .body {
            padding: 30px;
            background-color: #ffffff;
        }
        .content-cell {
            padding: 20px;
            text-align: left;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
        h1 {
            color: #333;
            font-size: 24px;
            text-align: center;
            margin-bottom: 20px;
        }
        p {
            margin-bottom: 15px;
            text-align: left;
        }
        /* Button styling */
        .button-wrapper {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            background-color: #dc3545; /* Bootstrap Red */
            border: none;
            color: #ffffff;
            padding: 12px 25px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .content {
                width: 90% !important;
            }
            .inner-body, .footer {
                width: 100% !important;
            }
            .content-cell {
                padding: 15px !important;
            }
        }
    </style>
</head>
<body>
    <table class="wrapper" width="100%" height="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center" valign="top">
                <table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    {{-- Header --}}
                    <tr>
                        <td class="header">
                            <a href="{{ url('/') }}" style="display: inline-block;">
                                <img src="{{ asset('assets/images/logos/Rena-Logo.webp') }}" alt="Rena Logo" style="max-width: 150px; height: auto; display: block; margin: 0 auto;">
                            </a>
                        </td>
                    </tr>

                    {{-- Email Body --}}
                    <tr>
                        <td class="body" width="100%" cellpadding="0" cellspacing="0">
                            <table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                {{-- Body content --}}
                                <tr>
                                    <td class="content-cell">
                                        <h1>Admin Account Created</h1>

                                        <p>Hello {{ $name ?? 'there' }}!</p>

                                        <p>Your admin account for <strong>{{ $customerName }}</strong> has been successfully created.</p>

                                        <p>Your default password is:</p>

                                        <p style="font-size: 18px; font-weight: bold; text-align: center; letter-spacing: 1px;">{{ $defaultPassword }}</p>

                                        <p>You can log in immediately using the button below. We recommend changing your password afterwards from your profile page.</p>

                                        <div class="button-wrapper">
                                            <a href="{{ $signedUrl }}" class="button">
                                                Log In
                                            </a>
                                        </div>

                                        <p>This link is valid for {{ config('auth.passwords.users.expire') }} minutes.</p>

                                        <p>If you were not expecting this account creation, please ignore this email.</p>

                                        <p>Regards,<br>{{ config('app.name') }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td>
                            <table class="footer" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td class="content-cell" align="center" style="text-align: center;">
                                        © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>