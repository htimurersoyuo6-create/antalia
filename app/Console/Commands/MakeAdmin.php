<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdmin extends Command
{
    protected $signature = 'petotel:admin {email} {--name=Yönetici} {--reset : Mevcut kullanıcının şifresini de yeniden belirle}';
    protected $description = 'Verilen e-posta için admin kullanıcı oluşturur veya yükseltir';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user || $this->option('reset')) {
            $pass = $this->secret('Şifre (en az 10 karakter)');
            if (strlen((string) $pass) < 10) { $this->error('Şifre çok kısa.'); return self::FAILURE; }
            if ($pass !== $this->secret('Şifre (tekrar)')) { $this->error('Şifreler uyuşmuyor.'); return self::FAILURE; }
            if (!$user) $user = new User(['name' => $this->option('name'), 'email' => $this->argument('email')]);
            $user->password = $pass;
        }
        $user->role = 'admin';
        $user->save();
        $this->info('Admin hazır: '.$user->email);
        return self::SUCCESS;
    }
}
