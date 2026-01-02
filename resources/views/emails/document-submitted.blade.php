@extends('emails.layout')

@section('content')
    <h2>📝 New Document Awaiting Your Approval</h2>
    
    <p>Hello <strong>{{ $document->approvers()->where('step_order', 1)->first()->approver->name }}</strong>,</p>
    
    <p>A new document has been submitted and requires your approval.</p>
    
    <div class="info-box">
        <p><strong>Document:</strong> {{ $document->title }}</p>
        <p><strong>Submitted by:</strong> {{ $document->creator->name }}</p>
        <p><strong>Department:</strong> {{ $document->department->name ?? 'N/A' }}</p>
        <p><strong>Date:</strong> {{ $document->submitted_at->format('d/m/Y H:i') }}</p>
    </div>
    
    <div style="text-align: center;">
        <a href="{{ config('app.url') }}/app/documents/{{ $document->id }}" class="btn">
            Review Document
        </a>
    </div>
@endsection
