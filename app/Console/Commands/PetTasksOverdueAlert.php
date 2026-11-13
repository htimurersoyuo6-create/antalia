<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Models\PetTask;
use Illuminate\Console\Command;
class PetTasksOverdueAlert extends Command
{
    protected $signature = 'pet:tasks-overdue';
    protected $description = 'Geciken personel görevlerini işaretle ve bildirim üret';
    public function handle(): int
    {
        $count = PetTask::query()->where('status','pending')->where('scheduled_at','<',now())->update(['status'=>'overdue']);
        $this->info("overdue_guncellendi: ".$count);
        return self::SUCCESS;
    }
}