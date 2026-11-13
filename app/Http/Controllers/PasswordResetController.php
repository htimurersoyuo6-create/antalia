<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('auth.passwords.email');
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'E-posta adresinizi girin.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'email.max' => 'E-posta adresi çok uzun.',
        ]);

        try {
            Password::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);
        } catch (\Throwable $exception) {
            // Keep the public response identical for unknown accounts and mail failures.
            Log::warning('Password reset notification could not be dispatched.', [
                'exception' => get_class($exception),
                'origin' => basename($exception->getFile()) . ':' . $exception->getLine(),
            ]);
        }

        return back()->with('status', 'Bu e-posta adresi kayıtlıysa parola yenileme bağlantısı gönderildi. Gelen kutunuzu ve gereksiz klasörünü kontrol edin.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.passwords.reset', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'E-posta adresinizi girin.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'password.required' => 'Yeni parolanızı girin.',
            'password.min' => 'Yeni parola en az :min karakter olmalıdır.',
            'password.confirmed' => 'Parola alanları eşleşmiyor.',
        ]);

        $status = Password::reset([
            'token' => $data['token'],
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
            'password_confirmation' => $request->input('password_confirmation'),
        ], function ($user, string $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->getAuthIdentifier())
                    ->delete();
            }

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            Auth::logout();

            return redirect()->route('login')->with('status', 'Parolanız yenilendi. Yeni parolanızla giriş yapabilirsiniz.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Bağlantı geçersiz veya süresi dolmuş. Yeni bir parola yenileme bağlantısı isteyin.']);
    }
}
