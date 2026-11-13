<?php
namespace App\Support;
use Illuminate\Http\Request;
class Captcha
{
 public static function challenge(string $key): array
 {
  $a=random_int(12,29);$b=random_int(3,12);$op=random_int(0,1);
  if($op){$question="$a + $b";$answer=$a+$b;}else{$question="$a - $b";$answer=$a-$b;}
  session()->put('captcha.'.$key,['answer'=>(string)$answer,'issued'=>time()]);
  return ['question'=>$question,'started'=>time()];
 }
 public static function check(Request $r,string $key): bool
 {
  $c=$r->session()->pull('captcha.'.$key);
  $elapsed=time()-(int)$r->input('_captcha_started',0);
  $trap=trim((string)$r->input('_captcha_website',''));
  return is_array($c) && !$trap && $elapsed>=1 && $elapsed<=3600
   && abs((int)$c['issued']-(int)$r->input('_captcha_started',0))<=2
   && hash_equals((string)$c['answer'],trim((string)$r->input('_captcha_answer','')));
 }
}
