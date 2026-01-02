@extends('emails.layout')

@section('content')
    <h2>↩️ Document Recalled</h2>
    
    <p>Hello,</p>
    
    <p>The following document has been recalled and is no longer waiting for approval.</p>
    
    <div class="warning-box">
        <p><strong>Document:</strong> {{ $document->title }}</p>
        <p><strong>Recalled by:</strong> {{ $document->creator->name }}</p>
        <p><strong>Department:</strong> {{ $document->department->name ?? 'N/A' }}</p>
    </div>
    
    <p>No action is required from you.</p>
@endsection
