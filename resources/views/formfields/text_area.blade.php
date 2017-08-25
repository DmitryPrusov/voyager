<textarea rows="{{ config('voyager.formfields.textarea.rows', 2) }}" @if($row->required == 1) required @endif class="form-control" name="{{ $row->field }}" data-details="{{ json_encode($options) }}">@if(isset($dataTypeContent->{$row->field})){{ old($row->field, $dataTypeContent->{$row->field}) }}@elseif(isset($options->default)){{ old($row->field, $options->default) }}@else{{ old($row->field) }}@endif</textarea>