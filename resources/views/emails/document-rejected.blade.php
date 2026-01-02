@extends('emails.layout')

@section('content')
    <h2>❌ Document Rejected</h2>
    
    <p>Hello <strong>{{ $document->creator->name }}</strong>,</p>
    
    <p>Your document has been rejected by an approver.</p>
    
    <div class="danger-box">
        <p><strong>Document:</strong> {{ $document->title }}</p>
        <p><strong>Rejected by:</strong> {{ $approver->approver->name }}</p>
        <p><strong>Date:</strong> {{ $approver->rejected_at->format('d/m/Y H:i') }}</p>
    </div>
    
    @if($approver->comment)
        <div class="warning-box">
            <p><strong>Reason:</strong></p>
            <p>{{ $approver->comment }}</p>
        </div>
    @endif
    
    <div style="text-align: center;">
        <a href="{{ config('app.url') }}/app/documents/{{ $document->id }}" class="btn">
            View Document
        </a>
    </div>
@endsection
