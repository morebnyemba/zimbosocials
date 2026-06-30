@extends('errors.layout')

@section('code', '500')
@section('label', 'Server Error')
@section('title', 'Something went wrong on our end')
@section('message', 'An unexpected error occurred. Our team has been notified. Please try again in a few minutes.')

@section('actions')
    <a class="btn btn-ghost" href="{{ route('marketing.contact') }}">Contact support</a>
@endsection
