<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLinkNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Antalya Pet Otel parola yenileme bağlantısı')
            ->greeting('Merhaba!')
            ->line('Hesabınız için parola yenileme isteği aldık.')
            ->action('Yeni parola belirle', $url)
            ->line('Bağlantı güvenliğiniz için 60 dakika geçerlidir.')
            ->line('Bu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz.');
    }
}
