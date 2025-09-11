@php
    /** @var \Platform\Helpdesk\Models\HelpdeskTicket $printable */
    /** @var \Platform\Printing\Models\PrintJob $job */
@endphp

========================================
           TICKET #{{ $printable->id }}
========================================

Titel: {{ $printable->title }}
Status: {{ $printable->status?->label() ?? 'Kein Status' }}
Priorität: {{ $printable->priority?->label() ?? 'Keine Priorität' }}

@if($printable->description)
Beschreibung:
{{ $printable->description }}
@endif

----------------------------------------
DETAILS:
----------------------------------------
Erstellt: {{ $printable->created_at->format('d.m.Y H:i') }}
@if($printable->due_date)
Fällig: {{ $printable->due_date->format('d.m.Y') }}
@endif
@if($printable->userInCharge)
Zugewiesen an: {{ $printable->userInCharge->name }}
@endif
@if($printable->helpdeskBoard)
Board: {{ $printable->helpdeskBoard->name }}
@endif

@if($printable->sla)
----------------------------------------
SLA INFORMATION:
----------------------------------------
SLA: {{ $printable->sla->name }}
@if($printable->sla->response_time_hours)
Reaktionszeit: {{ $printable->sla->response_time_hours }}h
@endif
@if($printable->sla->resolution_time_hours)
Lösungszeit: {{ $printable->sla->resolution_time_hours }}h
@endif
@endif

@if(isset($data['requested_by']))
----------------------------------------
Gedruckt von: {{ $data['requested_by'] }}
@endif

========================================
Gedruckt am: {{ now()->format('d.m.Y H:i:s') }}
========================================
