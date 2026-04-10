<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 30px; max-width: 480px; margin: auto; }
        .otp { font-size: 36px; font-weight: bold; letter-spacing: 10px; color: #1A73E8;
               text-align: center; padding: 20px 0; }
        p { color: #5F6368; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color:#202124">Verify your email</h2>
        <p>Use the code below to verify your account. It expires in <strong>10 minutes</strong>.</p>
        <div class="otp">{{ $otp }}</div>
        <p>If you did not request this, please ignore this email.</p>
    </div>
</body>
</html>