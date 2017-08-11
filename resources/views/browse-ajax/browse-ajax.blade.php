@extends('voyager::master')

@section('page_title','All '.$dataType->display_name_plural)

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-news"></i> {{ $dataType->display_name_plural }}
        @if (Voyager::can('add_'.$dataType->name))
            <a href="{{ route('voyager.'.$dataType->slug.'.create') }}" class="btn btn-success">
                <i class="voyager-plus"></i> Add New
            </a>
        @endif
    </h1>
    @include('voyager::multilingual.language-selector')
@stop

@section('content')
    <div class="page-content container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body">

                        <table id="data-ajax" class="table table-hover">

                            <thead>
                                <tr>
                                    @foreach($dataType->browseRows as $row)
                                       @if ($row->field == 'title')
                                       <th>{{ $row->display_name }}</th>
                                       @else
                                       <th>{{ $row->display_name }}</th>                                       
                                       @endif
                                    @endforeach
                                    <th class="actions">Actions</th>
                                </tr>
                            </thead>
                           
                           <!-- table body inserted by ajax -->

                            <tfoot>
                                <tr>
                                    @foreach($dataType->browseRows as $row)
                                       @if ($row->field == 'title')
                                       <th>{{ $row->display_name }}</th>
                                       @else
                                       <th>{{ $row->display_name }}</th>                                       
                                       @endif
                                    @endforeach
                                    <th class="actions">Actions</th>
                                </tr>
                            </tfoot>

                        </table>

                        <!-- pagination links -->

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">
                        <i class="voyager-trash"></i> Are you sure you want to delete this {{ $dataType->display_name_singular }}?
                    </h4>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('voyager.'.$dataType->slug.'.destroy', ['id' => '__id']) }}" id="delete_form" method="POST">
                        {{ method_field("DELETE") }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm" value="Yes, Delete This {{ $dataType->display_name_singular }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
@stop


@section('javascript')

    {{-- DataTables --}}
    <script>

        $(function() {

            var list = $('#data-ajax').DataTable({
                "order" : {!! $orderSettings !!},
                processing: true,
                serverSide: true,
                ajax: {
                  url : "{{ route('voyager.bread-ajax.datatable') }}",
                  type: 'GET',
                  data : {
                      slug : "{{ $dataType->slug }}"
                  }
                },
                columns: {!! $datatableColumnsData !!}
            });

        });


        $('#data-ajax').on('click', 'td .delete', function(e) {
            $('#delete_form')[0].action = $('#delete_form')[0].action.replace('__id', $(this).attr('data-id') );
            $('#delete_modal').modal('show');
        });


    </script>


    @if($isModelTranslatable)
        <script src="{{ voyager_asset('js/multilingual.js') }}"></script>
    @endif

@stop
