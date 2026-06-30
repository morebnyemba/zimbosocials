@extends('errors.layout')

@section('code', '404')
@section('label', 'Not Found')
@section('title', 'We can’t find that page')
@section('message', 'The page you’re looking for may have been moved, renamed, or never existed. Let’s get you back on track.')

@section('actions')
    <a class="btn btn-ghost" href="{{ url('/services') }}">Browse services</a>
@endsection
