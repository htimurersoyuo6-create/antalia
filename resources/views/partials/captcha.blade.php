@php($cap = \App\Support\Captcha::challenge($captchaKey))
<div class="captcha-box">
 <label for="captcha-{{ $captchaKey }}">Güvenlik kontrolü: {{ $cap['question'] }} kaç eder?</label>
 <input id="captcha-{{ $captchaKey }}" type="number" name="_captcha_answer" inputmode="numeric" required autocomplete="off" aria-describedby="captcha-help-{{ $captchaKey }}">
 <small id="captcha-help-{{ $captchaKey }}">Spam gönderimlerini önlemek için kısa doğrulama.</small>
 <div class="captcha-trap" aria-hidden="true"><label>Bu alanı boş bırakın<input name="_captcha_website" tabindex="-1" autocomplete="off"></label></div>
 <input type="hidden" name="_captcha_started" value="{{ $cap['started'] }}">
</div>
