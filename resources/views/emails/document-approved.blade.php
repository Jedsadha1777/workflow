@extends('emails.layout')

@section('content')
    @if($isFinalApproval)
        <h2>✅ Document Fully Approved</h2>
        
        <p>Hello <strong>{{ $document->creator->name }}</strong>,</p>
        
        <p>Your document has been fully approved by all approvers.</p>
        
        <div class="success-box">
            <p><strong>Document:</strong> {{ $document->title }}</p>
            <p><strong>Final Approver:</strong> {{ $approver->approver->name }}</p>
            <p><strong>Date:</strong> {{ $approver->approved_at->format('d/m/Y H:i') }}</p>
            @if($approver->comment)
                <p><strong>Comment:</strong> {{ $approver->comment }}</p>
            @endif
        </div>
        
        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/app/documents/{{ $document->id }}" class="btn">
                View Document
            </a>
        </div>
    @else
        @php
            $nextApprover = $document->approvers()->where('step_order', $document->current_step)->first();
        @endphp
        
        <h2>✅ Document Approved - Your Turn</h2>
        
        <p>Hello <strong>{{ $nextApprover->approver->name }}</strong>,</p>
        
        <p>A document has been approved and is now waiting for your review.</p>
        
        <div class="info-box">
            <p><strong>Document:</strong> {{ $document->title }}</p>
            <p><strong>Submitted by:</strong> {{ $document->creator->name }}</p>
            <p><strong>Approved by:</strong> {{ $approver->approver->name }} (Step {{ $approver->step_order }})</p>
            @if($approver->comment)
                <p><strong>Comment:</strong> {{ $approver->comment }}</p>
            @endif
            <p><strong>Your Step:</strong> {{ $nextApprover->step_order }} of {{ $document->approvers()->count() }}</p>
        </div>
        
        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/app/documents/{{ $document->id }}" class="btn">
                Review Document
            </a>
        </div>
    @endif
@endsection
