<?php
namespace App\Support;

class IdentityNumber
{
    public static function hash(string $id): string
    {
        return hash_hmac('sha256', $id, (string) config('app.key'));
    }

    public static function isValid(string $id): bool
    {
        if (!preg_match('/^[1-9][0-9]{10}$/D', $id)) return false;
        $d = array_map('intval', str_split($id));
        $tenth = ((($d[0] + $d[2] + $d[4] + $d[6] + $d[8]) * 7) - ($d[1] + $d[3] + $d[5] + $d[7])) % 10;
        return (($tenth + 10) % 10) === $d[9] && array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
    }
}
