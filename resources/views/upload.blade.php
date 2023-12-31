@extends('statamic::layout')

@section('title', __('JSON Import'))

@section('content')

    <header class="mb-3"><h1>JSON Import</h1></header>

    <div class="card p-2 content">
        <form action="{{ cp_route('json-import.upload') }}" method="POST" enctype="multipart/form-data">
            {{ csrf_field() }}
            <h2 class="font-bold">JSON file</h2>
            <p class="text-grey text-sm my-1">Need to import from WP? You can upload the JSON file you have exported with this <a href="https://github.com/statamic/wordpress-to-statamic-exporter" target="_blank">WordPress plugin</a>.</p>
            <p class="text-grey text-sm my-1">Problem with the images import? Try to set "set_images_as" config to "id" or "object".</p>
            <p class="text-grey text-sm my-1">Some field doesn't stored properly? Try to set "set_data_using_fields" config to true, it will process data using blueprint's field as normal CP controller.</p>
            <div class="flex justify-between items-center">
                <div class="pr-4">
                    <input type="file" class="form-control" name="file" />
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </div>
        </form>
    </div>

@endsection