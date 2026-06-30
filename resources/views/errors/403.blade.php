@extends('errors.layout')

@section('code', '403')
@section('label', 'Forbidden')
@section('title', 'You don’t have access to this')
@section('message', 'This page or action is restricted to its owner or an administrator. If you believe this is a mistake, our team can help.')

@section('actions')
    <a class="btn btn-ghost" href="{{ route('marketing.contact') }}">Contact support</a>
@endsection
