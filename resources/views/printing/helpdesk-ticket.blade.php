@php
    /** @var \Platform\Helpdesk\Models\HelpdeskTicket $printable */
    /** @var \Platform\Printing\Models\PrintJob $job */
    
    // Bon-Drucker optimierte Formatierung
    $width = 48; // 80mm = ~48 Zeichen
    $separator = str_repeat('=', $width);
    $line = str_repeat('-', $width);
@endphp

{{ $separator }}
{{ str_pad('TICKET #' . $printable->id, $width, ' ', STR_PAD_BOTH) }}
{{ $separator }}

{{ str_pad('TITEL:', 15, ' ') }}{{ Str::limit($printable->title, $width - 15) }}
{{ str_pad('ERLEDIGT:', 15, ' ') }}{{ $printable->is_done ? 'Ja' : 'Nein' }}
{{ str_pad('PRIORITAT:', 15, ' ') }}{{ $printable->priority?->label() ?? 'Keine' }}

@if($printable->description)
{{ $line }}
{{ str_pad('BESCHREIBUNG:', 15, ' ') }}
{{ wordwrap($printable->description, $width, "\n", true) }}
@endif

{{ $line }}
{{ str_pad('DETAILS:', 15, ' ') }}
{{ str_pad('Erstellt:', 15, ' ') }}{{ $printable->created_at->format('d.m.Y H:i') }}
@if($printable->due_date)
{{ str_pad('Fällig:', 15, ' ') }}{{ $printable->due_date->format('d.m.Y') }}
@endif
@if($printable->userInCharge)
{{ str_pad('Zugewiesen:', 15, ' ') }}{{ Str::limit($printable->userInCharge->name, $width - 15) }}
@endif
@if($printable->helpdeskBoard)
{{ str_pad('Board:', 15, ' ') }}{{ Str::limit($printable->helpdeskBoard->name, $width - 15) }}
@endif

@if($printable->sla)
{{ $line }}
{{ str_pad('SLA INFO:', 15, ' ') }}
{{ str_pad('SLA:', 15, ' ') }}{{ Str::limit($printable->sla->name, $width - 15) }}
@if($printable->sla->response_time_hours)
{{ str_pad('Reaktionszeit:', 15, ' ') }}{{ $printable->sla->response_time_hours }}h
@endif
@if($printable->sla->resolution_time_hours)
{{ str_pad('Lösungszeit:', 15, ' ') }}{{ $printable->sla->resolution_time_hours }}h
@endif
@endif

@if(isset($data['requested_by']))
{{ $line }}
{{ str_pad('Gedruckt von:', 15, ' ') }}{{ Str::limit($data['requested_by'], $width - 15) }}
@endif

{{ $separator }}
{{ str_pad(now()->format('d.m.Y H:i:s'), $width, ' ', STR_PAD_BOTH) }}
{{ $separator }}

{{ "\n\n\n" }}
