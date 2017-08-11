    @if (Voyager::can('delete_'.$dataType->name))
        <div class="btn-sm btn-danger pull-right delete" data-id="{{ $data->id }}">
            <i class="voyager-trash"></i>
        </div>
    @endif
    @if (Voyager::can('edit_'.$dataType->name))
        <a href="{{ asset('admin/'.$dataType->slug.'/'.$data->id.'/edit') }}" class="btn-sm btn-primary pull-right edit">
            <i class="voyager-edit"></i>
        </a>
    @endif
    @if (Voyager::can('read_'.$dataType->name))
        <a href="{{ asset('admin/'.$dataType->slug.'/'.$data->id) }}" class="btn-sm btn-warning pull-right">
            <i class="voyager-eye"></i>
        </a>
    @endif
