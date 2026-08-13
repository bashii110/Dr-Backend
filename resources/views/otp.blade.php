<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        {{ $type === 'password_reset' ? 'Reset Password' : 'Verify Email' }}
    </title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f7fb;
            font-family: Arial, Helvetica, sans-serif;
        }

        .wrapper {
            width: 100%;
            padding: 40px 15px;
            box-sizing: border-box;
        }

        .card {
            max-width: 500px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px 30px;
            box-sizing: border-box;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-icon {
            display: inline-block;
            width: 60px;
            height: 60px;
            line-height: 60px;
            border-radius: 16px;
            background-color: #1A73E8;
            color: #ffffff;
            font-size: 28px;
            font-weight: bold;
        }

        h1 {
            margin: 0 0 12px;
            text-align: center;
            color: #202124;
            font-size: 25px;
        }

        .hello {
            color: #202124;
            font-size: 16px;
            margin-bottom: 10px;
        }

        p {
            color: #5F6368;
            font-size: 15px;
            line-height: 1.6;
        }

        .otp-box {
            margin: 30px 0;
            padding: 22px;
            text-align: center;
            background-color: #f0f6ff;
            border: 1px solid #d5e6ff;
            border-radius: 12px;
        }

        .otp {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #1A73E8;
        }

        .expiry {
            text-align: center;
            font-size: 13px;
            color: #777;
            margin-top: 10px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
            text-align: center;
            color: #888888;
            font-size: 12px;
            line-height: 1.5;
        }
    </style>
</head>

<body>

<div class="wrapper">

    <div class="card">

        <div class="logo">
            <span class="logo-icon">+</span>
        </div>

        @if($type === 'password_reset')

            <h1>Reset Your Password</h1>

            <p class="hello">
                Hello <strong>{{ $user->name }}</strong>,
            </p>

            <p>
                We received a request to reset your Doctor App password.
                Use the verification code below to continue.
            </p>

        @else

            <h1>Verify Your Email</h1>

            <p class="hello">
                Hello <strong>{{ $user->name }}</strong>,
            </p>

            <p>
                Thank you for creating your Doctor App account.
                Please use the verification code below to verify your email address.
            </p>

        @endif

        <div class="otp-box">
            <div class="otp">
                {{ $otp }}
            </div>

            <div class="expiry">
                This code expires in <strong>10 minutes</strong>.
            </div>
        </div>

        <p>
            If you did not request this, please ignore this email.
            Your account remains secure.
        </p>

        <div class="footer">
            © {{ date('Y') }} Doctor App<br>
            This is an automated email. Please do not reply.
        </div>

    </div>

</div>

</body>
</html>