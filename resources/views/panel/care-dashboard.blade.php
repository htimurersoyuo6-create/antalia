@extends('layouts.app')
@section('title', 'Bakım panosu')
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('panel.index') }}">Panel</a> › Bakım panosu</div>
  <h1>Bugün / geciken görevler</h1>
  @if($overdue->isEmpty() && $upcoming->isEmpty())<p class="hint">Bekleyen görev yok.</p>@endif
  @if($overdue->isNotEmpty())
  <section class="cardbox"><h2 style="color:#b42318">Geciken görevler</h2>
    <table class="table"><tr><th>Rezervasyon</th><th>Evcil hayvan</th><th>Görev</th><th>Zaman</th><th>İşlem</th></tr>
    @foreach($overdue as $task)<tr><td>{{ $task->reservation->code }}</td><td>{{ $task->reservation->pet->name }}</td><td><b>{{ $task->title }}</b> · {{ $task->task_type }}</td><td>{{ $task->scheduled_at->format('d.m.Y H:i') }}</td><td><form method="post" action="{{ route('panel.care.task.complete',$task) }}">@csrf @method('PATCH')<button class="btn sm">Tamamlandı</button></form></td></tr>@endforeach
    </table>
  </section>
  @endif
  @if($upcoming->isNotEmpty())
  <section class="cardbox" style="margin-top:16px"><h2>Sıradaki görevler</h2>
    <table class="table"><tr><th>Rezervasyon</th><th>Evcil hayvan</th><th>Görev</th><th>Zaman</th><th>İşlem</th></tr>
    @foreach($upcoming as $task)<tr><td>{{ $task->reservation->code }}</td><td>{{ $task->reservation->pet->name }}</td><td><b>{{ $task->title }}</b> · {{ $task->task_type }}</td><td>{{ $task->scheduled_at->format('d.m.Y H:i') }}</td><td><form method="post" action="{{ route('panel.care.task.complete',$task) }}">@csrf @method('PATCH')<button class="btn sm ghost">Tamamlandı</button></form></td></tr>@endforeach
    </table>
  </section>
  @endif
  <section class="cardbox" style="margin-top:16px"><h2>Son günlük bakım karneleri</h2>
    @forelse($reports as $report)<article style="padding:12px;border-top:1px solid #e8ece7"><b>{{ $report->reservation->code }}</b> · {{ $report->pet->name }} · {{ $report->report_date->format('d.m.Y') }} · Beslenme: {{ $report->feeding_status }} · Tuvalet: {{ $report->toilet_status }} · Ruh hali: {{ $report->mood_status }} @if($report->notes)<br><small>{{ $report->notes }}</small>@endif</article>@empty<p class="hint">Karnesi kaydedilmemiş.</p>@endforelse
  </section>
</div>
@endsection