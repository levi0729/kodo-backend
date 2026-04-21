<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f17; color: #e0e0e0; margin: 0; padding: 40px 20px; }
        .container { max-width: 440px; margin: 0 auto; background: #1a1a2e; border-radius: 16px; padding: 40px; border: 1px solid rgba(255,255,255,0.06); }
        .logo { text-align: center; margin-bottom: 24px; font-size: 24px; font-weight: bold; color: #fff; }
        .code-box { background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.3); border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
        .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #818cf8; font-family: monospace; }
        .text { font-size: 14px; color: #a0a0b0; line-height: 1.6; }
        .btn { display: inline-block; background: #6366f1; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 12px; font-size: 14px; font-weight: 600; margin: 24px 0; }
        .footer { text-align: center; margin-top: 24px; font-size: 12px; color: #606070; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Kodo</div>
        <p class="text">Szia {{ $userName }},</p>
        <p class="text">Jelszó visszaállítási kérelmet kaptunk a fiókodhoz. Használd az alábbi kódot a jelszavad visszaállításához:</p>
        <div class="code-box">
            <div class="code">{{ $token }}</div>
        </div>
        <p class="text">A kód 60 percig érvényes. Ha nem te kérted, hagyd figyelmen kívül ezt az emailt — a jelszavad nem változik.</p>
        <div class="footer">Kodo &mdash; Team Management</div>
    </div>
</body>
</html>
