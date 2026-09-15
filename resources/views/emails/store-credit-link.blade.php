<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:32px 16px;background:#f7f3ee;font-family:Arial,Helvetica,sans-serif;color:#2b2420;">
    <table role="presentation" width="100%" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;">
        <tr>
            <td>
                <p style="margin:0 0 16px;font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;color:#a35a3a;">
                    Halal Brothers Live Poultry &amp; Meat
                </p>
                <p style="font-size:15px;line-height:1.6;">You asked to use your store credit on an online order. Open this link in the same browser you're checking out in:</p>
                <p style="margin:24px 0;"><a href="{{ $url }}" style="display:inline-block;background:#8a2a16;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 24px;border-radius:999px;">Use my store credit</a></p>
                <p style="font-size:13px;line-height:1.6;color:#6b625c;">The link works for {{ $minutes }} minutes. If you didn't ask for this, you can ignore this email — nobody can use your credit without it.</p>
            </td>
        </tr>
    </table>
</body>
</html>
