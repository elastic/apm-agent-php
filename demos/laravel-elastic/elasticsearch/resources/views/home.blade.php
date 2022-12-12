@extends('layout')

@section('content')
    @forelse ($posts as $post)
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">{{ $post->title }}</h5>
                <span class="small">{{ $post->summary }}</span>
                <p class="card-text">
                    {!! $post->content !!}
                </p>
            </div>
        </div>
    @empty
        <div class="card mt-3">
            <div class="card-body">
                There are no posts available
            </div>
        </div>
    @endforelse
@endsection
