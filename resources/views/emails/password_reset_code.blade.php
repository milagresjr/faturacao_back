<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recuperação de Senha</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f7; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { color: #333; }
        p { color: #555; font-size: 15px; line-height: 1.5; }
        .code-box { background: #f0f0f0; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; color: #2d3748; border-radius: 6px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Olá, {{ $user->name }}</h2>

        <p>Recebemos uma solicitação para redefinir a senha da sua conta no <strong>Zimboweb</strong>.</p>
        <p>Para continuar, utilize o código abaixo:</p>

        <div class="code-box">
            🔑 {{ $code }}
        </div>

        <p>Este código é válido até <strong>{{ \Carbon\Carbon::parse($expiresAt)->format('H:i') }}</strong>.</p>
        <p>Se você não solicitou essa alteração, ignore este e-mail.</p>

        <div class="footer">
            Atenciosamente,<br>
            Equipe Zimboweb
        </div>
    </div>
</body>
</html>
