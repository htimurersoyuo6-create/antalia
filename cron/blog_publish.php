<?php
// Zamanlanmis gorev girisi. Ornek (aaPanel > Cron > Shell betigi), her 30 dakikada bir:
// sudo -u www /www/server/php/84/bin/php /www/wwwroot/antalyapetotel.com/cron/blog_publish.php >> /www/wwwroot/antalyapetotel.com/logs/cron.log 2>&1
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$durum = $kernel->call('petotel:blog-yayinla');
echo $kernel->output();
exit($durum);
