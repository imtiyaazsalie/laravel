@extends('components.layouts.print')

@section('title')
    Statement for {{ $startDate }} - {{ $endDate }}
@endsection

@section('content')
    @include('finance.statement-content')
@endsection

