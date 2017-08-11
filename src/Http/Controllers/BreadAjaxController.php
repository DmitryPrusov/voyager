<?php

namespace TCG\Voyager\Http\Controllers;

use Collective\Html\HtmlFacade as Html;
use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;
use Yajra\Datatables\Facades\Datatables;

class BreadAjaxController extends VoyagerBreadController
{
    public $use_translations = false;

    //***************************************
    //               ____
    //              |  _ \
    //              | |_) |
    //              |  _ <
    //              | |_) |
    //              |____/
    //
    //      Browse our Data Type (B)READ
    //
    //****************************************

    public function index(Request $request)
    {
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $slug = $this->getSlug($request);

        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        Voyager::canOrFail('browse_'.$dataType->name);

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
        } else {
            $model = false;
        }

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($model);

        $datatableColumnsData = $this->getDatatableColumnsDataJson($dataType);

        $orderSettings = json_encode( $this->getOrderSettings($dataType) );

        $view = 'voyager::bread.browse';

        if (view()->exists("voyager::browse-ajax.browse-ajax")) {
            $view = "voyager::browse-ajax.browse-ajax";
        }

        if (view()->exists("voyager::$slug.browse-ajax")) {
            $view = "voyager::$slug.browse-ajax";
        }

        return view($view,
            compact('dataType', 'isModelTranslatable', 'datatableColumnsData', 'orderSettings'));
    }



    // action - return AJAX response with json data for 'datatable' js plugin
    public function datatableAjax(Request $request)
    {
        
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $slug = $request->slug;

        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        Voyager::canOrFail('browse_'.$dataType->name);

        $model = app($dataType->model_name);

        $query = $model::query();

        $entity_table = $dataType->name;
        $custom_columns_names = [];
        $columns_with_relation = [];

        foreach ($dataType->browseRows as $column_info) {

            if ( $this->isCustomViewColumn($column_info) ) {
                $custom_columns_names[] = $column_info->field;
            }

            if ( $this->isCustomViewColumnWithRelation($column_info) ) {
                $columns_with_relation[] = $column_info->field;
                $relation_table = $this->getColumnDataSourceTable($column_info);
                $relation_column = $this->getColumnDataSourceColumnName($column_info);
                $local_key = $this->getColumnDataSourceLocalKey($column_info);
                $foreign_key = $this->getColumnDataSourceForeignKey($column_info);
                $query->addSelect( "$relation_table.$relation_column AS {$column_info->field}" );
                $query->leftJoin($relation_table,
                    "$relation_table.$foreign_key", '=', "$entity_table.$local_key");
            } else {
                $query->addSelect( "$entity_table.{$column_info->field} AS {$column_info->field}" );
            }
        }

        $datatables = Datatables::eloquent( $query );

        if ( !empty($columns_with_relation) && !empty( request('search')['value'] ) ){
            $search = request('search')['value'];
            $searchable_custom_columns = [];

            foreach($dataType->browseRows as $column_info) {
                if ( $this->isCustomViewColumnWithRelation($column_info) &&
                     $this->isColumnSearchable($column_info) ) {
                    $searchable_custom_columns[] = $column_info;
                }
            }

            if ( !empty($searchable_custom_columns) ) {
                $datatables->filter( function ($query) use($searchable_custom_columns, $search) {

                    foreach ($searchable_custom_columns as $column_info) {
                        $relation_table = $this->getColumnDataSourceTable($column_info);
                        $relation_column = $this->getColumnDataSourceColumnName($column_info);
                        $query->orWhere("$relation_table.$relation_column", 'like', "%$search%");
                    }

                }, true);
            }
        }

        foreach($dataType->browseRows as $index => $column_info) {
            if ( $this->isCustomViewColumn($column_info) ) {
                $datatables->editColumn($column_info->field, function ($data) use($column_info) {
                    return $this->renderCustomColumn($data, $column_info);
                });
            }
        }

        $datatables->addColumn('actions', function ($data) use($dataType) {
            //$data - model object
            return view('voyager::browse-ajax.crud-buttons', compact('data' ,'dataType') );
        });

        $datatables->rawColumns( array_merge($custom_columns_names, ['actions']) );

        return $datatables->make(true);
    }


    public function getOrderSettings($dataType)
    {
        $settings = [];

        foreach($dataType->browseRows as $index => $column) {
            if ( $details = $this->getDataTypeDetails($column) ) {

                if ( isset($details['ajax-datatable']['order-default']['direction']) ) {
                    $dir = $details['ajax-datatable']['order-default']['direction'];
                    if ( in_array($dir, ['ASC', 'DESC']) ) {
                        $settings[] = [$index, $dir];
                    }
                }

            }
        }

        return $settings;
    }


    public function getDatatableColumnsDataJson($dataType)
    {
        $data = [];

        foreach($dataType->browseRows as $index => $column) {

            $options = [ 'data' => $column->field, 'name' => $column->field ];

            if ( ! $this->isColumnOrderable($column) ){
                $options = array_merge( $options,
                    ['orderable' => false, 'className' => 'no-sort no-click'] );
            }

            if ( ! $this->isColumnSearchable($column) ){
                $options = array_merge( $options, ['searchable' => false]);
            }

            $data[] = $options;
        }

        // added actions buttons column
        $data[] = [
            'data' => 'actions',
            'name' => 'actions', 
            'orderable' => false, 
            'searchable' => false, 
            'className' => 'no-sort no-click'
        ];

        return json_encode($data);
    }


    public function isCustomViewColumn($columnInfo)
    {
        return $this->getAjaxDatatableOption('display-custom', $columnInfo) ? true : false;
    }


    public function isCustomViewColumnWithRelation($columnInfo)
    {
        return !empty(
            $this->getColumnDetail(
                'ajax-datatable.display-custom.data-source.table.table',
                $columnInfo) ) ? true : false;
    }


    /*  Функция возвращает html-вывод для кастомной колонки.

        Примеры опций для кастомных колонок:

        "type": "html",
        "css-style": "width:100px",
        "template" : "voyager::browse-ajax.bold-text",
        "data-source": {
            "table": {
                "table": "categories",
                "column": "name",
                "local-key": "category_id",
                "foreign-key": "id"
            }
        }

        Кастомная колонка может быть трех типов:
            "type": "default",
            "type": "html",
            "type": "image",
    */
    public function renderCustomColumn($data, $columnInfo)
    {
        $type = $this->getCustomViewColumnType($columnInfo);

        $template = $this->getColumnTemplate($columnInfo);

        $data_source_function = $this->getColumnDataSourceFunction($columnInfo);

        $data_source_table = $this->getColumnDataSourceTable($columnInfo);

        $field_value = $data->{$columnInfo->field};

        if ( $data_source_function && !$data_source_table ) {
            $field_value = call_user_func([$data, $data_source_function]);
        }

        switch ($type) {
            case 'default':
                // displaying of simple string field value with escaping html-entitles
                return Html::entities($field_value);
                break;

            case 'html':
                if ($template == null) {
                    // displaying of simple string field value WITHOUT escaping html-entitles
                    return $field_value;
                }
                $render_data = [ 'field_value' => $field_value,
                    'column_info' => $columnInfo, 'model_object' => $data ];
                return view( $template, [ 'data' => $render_data ] ); // displaying of custom blade-template
                break;

            case 'image':
                // displaying of image
                if ($template == null) {
                    $template = 'voyager::browse-ajax.image';
                    $folder = $this->getColumnImageFolder($columnInfo) ?
                        $this->getColumnImageFolder($columnInfo) . '/' : 'storage/';
                    $render_data = [
                        'url' => url($folder . $field_value),
                        'css-style' => $this->getColumnCssStyle($columnInfo) ? : '',
                    ];
                } else {
                    $render_data = [ 'field_value' => $field_value,
                        'column_info' => $columnInfo, 'model_object' => $data ];
                }

                return view($template, ['data' => $render_data]);
                break;
        }

        return '';
    }


    public function getCustomViewColumnType($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.type', $column);
    }


    public function isColumnOrderable($column)
    {
        if ( $this->getCustomViewColumnType($column) == 'image' ) {
            return false;
        }

        return $this->getAjaxDatatableOption('orderable', $column) === false ? false : true;
    }


    public function isColumnSearchable($column)
    {
        if ( $this->getCustomViewColumnType($column) == 'image' ) {
            return false;
        }

        return $this->getAjaxDatatableOption('searchable', $column) === false ? false : true;
    }


    public function getColumnTemplate($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.template', $column);
    }


    public function getColumnDataSourceFunction($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.data-source.function', $column);
    }


    public function getColumnCssStyle($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.css-style', $column);
    }

    public function getColumnImageFolder($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.folder', $column);
    }

    public function getColumnDataSourceTable($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.data-source.table.table', $column);
    }


    public function getColumnDataSourceColumnName($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.data-source.table.column', $column);
    }


    public function getColumnDataSourceLocalKey($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.data-source.table.local-key', $column);
    }


    public function getColumnDataSourceForeignKey($column)
    {
        return $this->getColumnDetail(
            'ajax-datatable.display-custom.data-source.table.foreign-key', $column);
    }


    // example: $path = 'ajax-datatable.display-custom.data-source.table.column'
    public function getColumnDetail($path, $columnInfo)
    {
        if ( $details = $this->getDataTypeDetails($columnInfo, true) ) {
            $path = explode('.', $path);
            $detail = $details;
            foreach($path as $dimension) {
                if ( isset( $detail[$dimension] ) ) {
                    $detail = $detail[$dimension];
                } else {
                    return null;                    
                }
            }
            return $detail;
        }

        return null;
    }


    public function getDataTypeDetails($column, $assoc_array = true)
    {
        if ( isset($column->details) && !empty($column->details) ) {
            return json_decode($column->details, $assoc_array);
        }

        return null;
    }


    public function getAjaxDatatableOption($option, $column, $assoc_array = true)
    {
        if ( $details = $this->getDataTypeDetails($column, $assoc_array) ) {
            if ( isset($details['ajax-datatable'][$option]) ) {
                return $details['ajax-datatable'][$option];
            }
        }

        return null;
    }



}

/* -------------------------------

Для подключения списка с ajax для заданной сущности нужно 
в админке в настройке bread для сущности указать 
контроллер TCG\Voyager\Http\Controllers\BreadAjaxController.

Для каждой колонки с кастомным выводом нужно написать конфиг 
в формате json в поле details в админке в настройках bread 
для сущности.

Пример настроек кастомных колонок для таблицы articles :

// column: category_id
{
    "ajax-datatable": {
        "display-custom": {
            "type": "html",
            "template": "voyager::browse-ajax.bold-text",
            "data-source": {
                "table": {
                    "table": "categories",
                    //"model": "Category",
                    "column": "title",
                    "local-key": "category_id",
                    "foreign-key": "id"
                }
            }
        }
    }
}

// column: article_photo_id
{
    "ajax-datatable": {
        "display-custom": {
            "type": "image",
            "css-style": "width:100px",
            "data-source": {
                "table": {
                    "table": "article_photos",
                    //"model": "ArticlePhoto",
                    "column": "image",
                    "local-key": "article_photo_id",
                    "foreign-key": "id"
                }
            }
        }
    }
}

// column: author_id
{
    "ajax-datatable": {
        "display-custom": {
            "type": "default",
            "data-source": {
                "table": {
                    "table": "users",
                    //"model": "User",
                    "column": "name",
                    "local-key": "author_id",
                    "foreign-key": "id"
                }
            }
        }
    }
}

// column: active
{
    "ajax-datatable": {
        "searchable": false
    }
}

// column: published_at
{
    "ajax-datatable": {
        "order-default": {
            "direction": "DESC"
        }
    }
}


Пример настроек кастомных колонок для таблицы article_photo :

// column: image
{
    "ajax-datatable": {
        "display-custom": {
            "type": "image",
            "css-style": "width:100px",
            "folder": "storage"
        }
    }
}


Пример настроек кастомных колонок для таблицы tags

// column: text_full
{
    "ajax-datatable": {
        "display-custom": {
            "type": "html"
        }
    }
}

// column: image
{
    "ajax-datatable": {
        "display-custom": {
            "type": "image",
            "css-style": "width:100px",
            "folder": "storage"
        }
    }
}

------------------------------ */



