@php ($id = 'input_' . $row->field)

<input id="{{ $id }}" type="hidden" name="{{ $row->field }}"
    value="{{ old($row->field) }}">

@foreach($options->crop as $photoParams)
    <input type="hidden" name="{{ $row->field . '_' . $photoParams->name }}"
       value="{{ old($row->field . '_' . $photoParams->name ) }}">
@endforeach

<button type="button" class="btn btn-primary" id="upload">
    <i class="voyager-upload"></i> Upload
</button>
<div id="uploadPreview" style="display:none;"></div>

<div id="dropzone">
@foreach($options->crop as $photoParams)
    <div class="foto-send">
        <div class="photo-block {{ $photoParams->name }}">
            <div class="cropMain"></div>
            <div class="cropSlider"></div>
        </div>
    </div>
@endforeach
</div>

<style>
@foreach($options->crop as $photoParams)
    .{{ $photoParams->name }} .cropMain {
        width: {{ $photoParams->size->width / 2 }}px;
        height: {{ $photoParams->size->height / 2 }}px;
    }
    .{{ $photoParams->name }} .cropSlider {
        width: {{ $photoParams->size->width / 2 }}px;
    }
@endforeach
</style>

@section('javascript')
    @parent
<link rel="stylesheet" href="{{ voyager_asset('js/plugins/crop/crop.min.css') }}">
<script src="{{ voyager_asset('js/plugins/crop/crop.min.js') }}"></script>

<script>
$(document).ready(function() {
    @foreach($options->crop as $photoParams)
        var {{ $photoParams->name }} = new CROP;
        {{ $photoParams->name }}.init(".{{ $photoParams->name }}");

        @if ( old($row->field) )
            {{ $photoParams->name }}.loadImg("{{ storage_url(old($row->field)) }}?{{ time() }}");
        @elseif ( isset($dataTypeContent->{$row->field}) )
            {{ $photoParams->name }}.loadImg("{{ storage_url($dataTypeContent->{$row->field}) }}");
        @endif
    @endforeach
});

CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

$("#upload, #dropzone").dropzone({
    url: "{{ route('voyager.media.upload') }}",
    previewsContainer: "#uploadPreview",

    sending: function(file, xhr, formData) {
        formData.append("_token", CSRF_TOKEN);
        formData.append("upload_path", "{{ $dataType->slug . '/' . date('F') . date('Y') }}");
    },
    success: function(e, res){
        if (res.success){
            $("div").remove(".crop-container");
            $("div").remove(".noUi-base");

            $('#{{ $id }}').val(res.path);

            @foreach($options->crop as $photoParams)
                var {{ $photoParams->name }} = new CROP;
                {{ $photoParams->name }}.init(".{{ $photoParams->name }}");
                {{ $photoParams->name }}.loadImg("/{{ basename(storage_url('')) }}/" + res.path);

                $("button:submit").click(function() {
                    $('input[name={{ $row->field . '_' . $photoParams->name }}]').val(
                        JSON.stringify(coordinates({{ $photoParams->name }}))
                    );
                })
            @endforeach

            toastr.success(res.message, "Sweet Success!");
        } else {
            toastr.error(res.message, "Whoopsie!");
        }
    },
    error: function(e, res, xhr){
        toastr.error(res, "Whoopsie");
    }
});
</script>
@endsection