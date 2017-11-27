<?php

namespace D4nd3v\Crud;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use League\Flysystem\Directory;

class CrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:crud {table} {--model_only=false} {--child_of=} {--parent_of=} {--many_with=} {--overwrite=false} {--crud=CRUD} {--upload=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a complete customizable Laravel CRUD Resource with one command.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */


    private $overwriteExistingFiles = false;
    private $templatePath = (__DIR__ . '/templates/');


    private $schema = array();



    private $upload = null;
    private $manyWith = null;


    public function handle()
    {
        $tableName = $this->argument('table');
        $modelOnly = $this->option('model_only');
        $parentOf = $this->option('parent_of'); // table_1.fk1,table_2.fk2,table_3.fk3
        $childOf = $this->option('child_of'); // TABLE
        $manyWith = $this->option('many_with');
        $this->manyWith = $this->option('many_with');
        $this->upload = $this->option('upload', null);


        $crud = strtolower($this->option('crud'));

        if($this->option('overwrite')=="true") {
            $this->overwriteExistingFiles =  true;
        }



        $dbFields = \DB::select(\DB::raw("SHOW COLUMNS FROM ".$tableName.""));
        $this->schema[$tableName] = $dbFields;
        if(!empty($childOf)) {
            $parents = explode(',', $childOf);
            foreach ($parents as $parent) {
                $this->schema[trim($parent)] = \DB::select(\DB::raw("SHOW COLUMNS FROM ".trim($parent).""));
            }
        }
        if(!empty($parentOf)) {
            $childs = explode(',', $parentOf);
            foreach ($childs as $child) {
                $this->schema[trim($child)] = \DB::select(\DB::raw("SHOW COLUMNS FROM ".trim($child).""));
            }
        }
        if(!empty($manyWith)) {
            $mw = explode(',', $manyWith);
            foreach ($mw as $m) {
                $this->schema[trim($m)] = \DB::select(\DB::raw("SHOW COLUMNS FROM ".trim($m).""));
            }
        }





        $this->createModel($tableName, $dbFields, $parentOf, $childOf, $manyWith);
        if($modelOnly!=="true") {
            $this->createRoutes($tableName, $childOf, $crud);
            $this->createController($tableName, $dbFields, !empty($childOf));
            $this->createViews($tableName, $dbFields, !empty($childOf), $childOf, $parentOf, $manyWith, $crud);
            $this->createLayouts();
        }

        $this->info("Done.");

    }





    public function createLayouts()
    {
        $destionationPath= resource_path('views/layouts/simple.blade.php');
        if(! \File::exists($destionationPath)) {
            $this->createFileFromTemplate($this->templatePath . '/simple_layout.txt', $destionationPath);
        }

    }



    private function createFileFromTemplate($source, $destination)
    {
        if(!\File::exists($destination) || $this->overwriteExistingFiles) {
            \File::put($destination, \File::get($source));
        } else {
            $this->warn('File: ' . $destination. ' already exist.');
        }
    }




    public function createViews($tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud)
    {
        // create view folder
        $viewsPath = resource_path('views') .'/'. $this->getViewFolder($tableName);
        if(!\File::exists($viewsPath)) {
            \File::makeDirectory($viewsPath, 0755, true);
        }
        $this->createCreateView($viewsPath, $tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud);
        $this->createEditView($viewsPath, $tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud);
        $this->createIndexView($viewsPath, $tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud);
        $this->createViewView($viewsPath, $tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud);
    }


    public function createCreateView($viewsPath, $tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud)
    {
        $pk = $this->getPrimaryKey($dbFields);
        $createViewTemplate = \File::get(($this->templatePath . 'views/create_blade.txt'));
        $viewCreatePath = $viewsPath. '/create.blade.php';
        $newRoutePath = $this->getRouteResourceName($tableName);
        $createViewTemplate = str_replace('$ROUTE$', $newRoutePath, $createViewTemplate);
        $createViewTemplate = str_replace('$FORM$', $this->getViewForms($dbFields, $childOf, $manyWith), $createViewTemplate);
        $createViewTemplate = $this->showHideParentInTemplate($createViewTemplate, $tableIsChild);
        $createViewTemplate = str_replace('$PRIMARY_KEY$', $pk, $createViewTemplate);

        $masterLayout = $this->getSimpleMasterLayout();
        if($masterLayout!="") {
            $createViewTemplate = str_replace('$MASTER_LAYOUT$', '@extends(\''.$masterLayout.'\')', $createViewTemplate);
        }

        $createViewTemplate = $this->applyAllViewsTemplateSetup($createViewTemplate);

        if(!\File::exists($viewCreatePath) || $this->overwriteExistingFiles) {
            \File::put($viewCreatePath, $createViewTemplate);
        }
    }


    public function createEditView($viewsPath, $tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud)
    {
        $pk = $this->getPrimaryKey($dbFields);
        $editViewTemplate = \File::get(($this->templatePath . 'views/edit_blade.txt'));
        $viewEditPath = $viewsPath. '/edit.blade.php';

        $editViewTemplate = str_replace('$ROUTE$', $this->getRouteResourceName($tableName), $editViewTemplate);
        $editViewTemplate = str_replace('$FORM$', $this->getViewForms($dbFields, $childOf, $manyWith, true), $editViewTemplate);
        $editViewTemplate = $this->showHideParentInTemplate($editViewTemplate, $tableIsChild);
        $editViewTemplate = str_replace('$PRIMARY_KEY$', $pk, $editViewTemplate);

        $masterLayout = $this->getSimpleMasterLayout();
        if($masterLayout!="") {
            $editViewTemplate = str_replace('$MASTER_LAYOUT$', '@extends(\''.$masterLayout.'\')', $editViewTemplate);
        }

        $editViewTemplate = $this->applyAllViewsTemplateSetup($editViewTemplate);

        if(!\File::exists($viewEditPath) || $this->overwriteExistingFiles) {
            \File::put($viewEditPath, $editViewTemplate);
        }
    }

    public function createViewView($viewsPath, $tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud)
    {
        $pk = $this->getPrimaryKey($dbFields);
        $editViewTemplate = \File::get(($this->templatePath . 'views/view_blade.txt'));
        $viewEditPath = $viewsPath. '/view.blade.php';

        $editViewTemplate = str_replace('$ROUTE$', $this->getRouteResourceName($tableName), $editViewTemplate);
        $editViewTemplate = str_replace('$FORM$', $this->getViewShowContent($dbFields, true), $editViewTemplate);
        $editViewTemplate = $this->showHideParentInTemplate($editViewTemplate, $tableIsChild);
        $editViewTemplate = str_replace('$PRIMARY_KEY$', $pk, $editViewTemplate);

        $masterLayout = $this->getSimpleMasterLayout();
        if($masterLayout!="") {
            $editViewTemplate = str_replace('$MASTER_LAYOUT$', '@extends(\''.$masterLayout.'\')', $editViewTemplate);
        }

        if(!\File::exists($viewEditPath) || $this->overwriteExistingFiles) {
            \File::put($viewEditPath, $editViewTemplate);
        }
    }


    public function applyAllViewsTemplateSetup($template)
    {

        if (!empty($this->upload)) { // DISABLED
            // remove markes, let multipart encrypt
            $template = str_replace('$IF_FILE_UPLOAD$', "", $template);
            $template = str_replace('$END_IF_FILE_UPLOAD$', "", $template);
        } else {
            // remove markers and text
            $template = preg_replace('~\$IF_FILE_UPLOAD\$.*?\$END_IF_FILE_UPLOAD\$~', '', $template);
        }

        return $template;
    }




    public function getPK($table)
    {
        return $this->getPrimaryKey($this->schema[$table]);
    }


    public function createIndexView($viewsPath, $tableName, $dbFields, $tableIsChild, $childOf, $parentOf, $manyWith, $crud)
    {
        $pk = $this->getPrimaryKey($dbFields);
        $newRoutePath = $this->getRouteResourceName($tableName);
        $indexViewTemplate = \File::get(($this->templatePath . 'views/index_blade.txt'));
        $viewIndexPath = $viewsPath. '/index.blade.php';
        $indexViewTemplate = str_replace('$ROUTE$', $newRoutePath, $indexViewTemplate);
        $indexViewTemplate = str_replace('$TABLE_HEADER$', $this->getViewIndexTableHeader($dbFields, $childOf), $indexViewTemplate);
        $indexViewTemplate = str_replace('$TABLE$', $this->getViewIndexTable($dbFields, $tableName, $childOf), $indexViewTemplate);
        $indexViewTemplate = $this->showHideParentInTemplate($indexViewTemplate, $tableIsChild);
        $childLinkHeader = "";
        $childLink = "";
        if(!empty($parentOf)) {
            $parentOfArray = explode(',', $parentOf);
            foreach ($parentOfArray as $pof) {
                $childLinkHeader .= PHP_EOL . '<th>&nbsp;</th>';
                // $childLink .= PHP_EOL . '<td><a href="/'.$this->getRouteResourceName($tableName).'/{{ $item->'.$pk.' }}/'.$pof.'">'.$pof.'</a></td>';
                $childLink .= PHP_EOL . '<td><a href="/'.$pof.'?filter['.str_singular($tableName).'_id]={{ $item->'.$pk.' }}">'.ucfirst($pof).'</a></td>';
            }
            // $indexViewTemplate = str_replace('$CHILD_LINK$', '<td width="1"><a href="{{ route(\''.$childTable.'.index\', [$item->id]) }}">'.$childTable.'</a></td>', $indexViewTemplate);
        }
        $indexViewTemplate = str_replace('$CHILD_LINK_HEADER$', $childLinkHeader, $indexViewTemplate);
        $indexViewTemplate = str_replace('$CHILD_LINK$', $childLink, $indexViewTemplate);
        $indexViewTemplate = str_replace('$PRIMARY_KEY$', $pk, $indexViewTemplate);




        $childLinkHeader = "";
        $childLink = "";

        if(!empty($manyWith)) {
            $manyWithParts = explode(',', $manyWith);
            foreach ($manyWithParts as $mw) {
                $childLinkHeader .= '<th>'. $this->formatColumnName($mw) .'</th>';
                $childLink .= '<td>
                    @if(isset($item->' . $mw . '))
                        @foreach($item->' . $mw . '()->get() as $mwItem)
                            &bull; {{ $mwItem->name }}
                        @endforeach
                    @endif
                </td>';
            }
        }
        $indexViewTemplate = str_replace('$MANYTO_LINK_HEADER$', $childLinkHeader, $indexViewTemplate);
        $indexViewTemplate = str_replace('$MANYTO_LINK$', $childLink, $indexViewTemplate);











        $masterLayout = $this->getMasterLayout();
        if($masterLayout!="") {
            $indexViewTemplate = str_replace('$MASTER_LAYOUT$', '@extends(\''.$masterLayout.'\')', $indexViewTemplate);
        }



        if( strpos( $crud, "c" ) !== false ) {
            // create is on, remove markers
            $indexViewTemplate = str_replace('$START_COMMENT_CREATE$', "", $indexViewTemplate);
            $indexViewTemplate = str_replace('$END_COMMENT_CREATE$', "", $indexViewTemplate);
        } else {
            // create is off, omment block
            $indexViewTemplate = str_replace('$START_COMMENT_CREATE$', "{{--", $indexViewTemplate);
            $indexViewTemplate = str_replace('$END_COMMENT_CREATE$', "--}}", $indexViewTemplate);
        }

        if( strpos( $crud, "d" ) !== false ) {
            // DELETE is on, remove markers
            $indexViewTemplate = str_replace('$START_COMMENT_DELETE$', "", $indexViewTemplate);
            $indexViewTemplate = str_replace('$END_COMMENT_DELETE$', "", $indexViewTemplate);
        } else {
            // DELETE is off, omment block
            $indexViewTemplate = str_replace('$START_COMMENT_DELETE$', "{{--", $indexViewTemplate);
            $indexViewTemplate = str_replace('$END_COMMENT_DELETE$', "--}}", $indexViewTemplate);
        }


        if( strpos( $crud, "u" ) !== false ) {
            // UPDATE is on, remove markers
            $indexViewTemplate = str_replace('$START_COMMENT_UPDATE$', "", $indexViewTemplate);
            $indexViewTemplate = str_replace('$END_COMMENT_UPDATE$', "", $indexViewTemplate);
        } else {
            // UPDATE is off, omment block
            $indexViewTemplate = str_replace('$START_COMMENT_UPDATE$', "{{--", $indexViewTemplate);
            $indexViewTemplate = str_replace('$END_COMMENT_UPDATE$', "--}}", $indexViewTemplate);
        }



        // add table filter before table
        // if this table has parents

        $selectFromParentHtml = "";
        if(!empty($childOf)) {
            $childOfArray = explode(',', $childOf);

            if(count($childOfArray)>0) {
                $selectFromParentHtml .= '
                <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
                <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>';
            }



            foreach ($childOfArray as $tbl) {

                $selectFromParentHtml .= '
                <form style="display:inline-block" class="form-inline" method="GET" action="{{ route(\'books.index\') }}">
                    <select id="author_id" name="filter[author_id]" class="form-control" onchange="this.form.submit()">
                        <option value="">--- All Authors ---</option>
                        @foreach(\App\Models\Author::get() as $parentItem)
                            <option value="{{ $parentItem->id }}"
                                    @if(request()->input("filter.author_id") == $parentItem->id)
                                        selected
                                    @endif>
                                {{ $parentItem->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
                <script type="text/javascript">
                    $("#author_id").select2();
                </script>';

            }


            if(!empty($selectFromParentHtml)) {
                $selectFromParentHtml .= '
                @if (isset(request()->filter))
                    @if (!empty(array_filter(request()->filter)))
                        <a href="{{ route(\'books.index\') }}" class="btn btn-default">
                            Reset
                        </a>
                    @endif
                @endif
                ';
            }












        }


        $indexViewTemplate = str_replace('$SELECT_FROM_PARENT$', $selectFromParentHtml, $indexViewTemplate);




        if(!\File::exists($viewIndexPath) || $this->overwriteExistingFiles) {
            \File::put($viewIndexPath, $indexViewTemplate);
        }
    }




    private function getMasterLayout()
    {
        $layoutFolder = resource_path('views/layouts/');
        if(\File::exists($layoutFolder)) {
            foreach (glob($layoutFolder . "*.blade.php") as $filename) {
                $x =  str_replace($layoutFolder, '', $filename);
                $x =  str_replace('.blade.php', '', $x);
                return 'layouts.'.$x;
            }
        }
        return  '';
    }

    private function getSimpleMasterLayout()
    {
        return 'layouts.simple';
    }



    public function showHideParentInTemplate($template, $tableIsChild)
    {
        if ($tableIsChild && false) { // DISABLED
            // remove markes
            $template = str_replace('$IF_HAS_PARENT$', "", $template);
            $template = str_replace('$END_HAS_PARENT$', "", $template);
        } else {
            // remove markers and text
            $template = preg_replace('~\$IF_HAS_PARENT\$.*?\$END_HAS_PARENT\$~', '', $template);
        }
        return $template;
    }











    public function getValidationRules($dbFields)
    {
        $pk = $this->getPrimaryKey($dbFields);
        $v = "";
        foreach ($dbFields as $c) {
            $fieldName = $c->Field;
            if($fieldName!=$pk && $fieldName!="created_at" && $fieldName!="updated_at" && $fieldName!="deleted_at") {

                $isFileUpload = false;
                if (!is_null($this->upload)) {
                    if (in_array($c->Field, explode(',', $this->upload))) {
                        $isFileUpload = true;
                    }
                }


                $fieldRules = array();
                if($c->Null=="NO" && is_null($c->Default)) {
                    if(!$this->columnIsString($c)) {
                        $fieldRules[] = "required";
                    }
                }
                $typeParts = explode('(', $c->Type);
                $size = 0;
                if(count($typeParts)>1) {
                    $size = str_replace(')','',$typeParts[1]);
                }
                $fieldType = $typeParts[0];
                if($fieldType=="varchar" && !$isFileUpload) {
                    $fieldRules[] = "max:".$size;
                }
                if($fieldType=="tinyint" || $fieldType=="int" || $fieldType=="smallint" || $fieldType=="mediumint" || $fieldType=="bigint") {
                    $fieldRules[] = "integer";
                }
                if($fieldType=="date" || $fieldType=="datetime" || $fieldType=="timestamp") {
                    $fieldRules[] = "date";
                }

                if ($isFileUpload) {
                    $fieldRules[] = "image|mimes:jpeg,jpg|max:1024";
                }

                $v .= PHP_EOL . "        '" . $fieldName . "'" . ' => ' . '"'.(implode('|',$fieldRules)).'",';
            }
        }
        return $v;
    }



    public function getViewIndexTableHeader($dbFields, $childOf)
    {

        $f = "";
        foreach ($dbFields as $column) {

            if($column->Field!="deleted_at") {

                $type = $column->Type;

                $name = $column->Field;




                if(!empty($childOf)) {
                    $parents = explode(',', $childOf);
                    foreach ($parents as $parent) {
                        if(str_singular($parent) . '_id' == $column->Field) {
                            $name = str_singular($parent);
                        }
                    }
                }



                if ($type == "text" || $type == "longtext" || $type == "binary" || $type == "varbinary") {
                    // do noy show big text columns, show comment
                    $f .= str_repeat(" ", 4 * 4) . '{{-- <th>'.($this->formatColumnName($name)).'</th> --}}'.PHP_EOL;
                } else {
                    $f .= str_repeat(" ", 4 * 4) . '<th>
                    <a href="{{ request()->fullUrlWithQuery(["order"=>($order=="asc"?"desc":"asc"), "by"=>"' . $column->Field . '", "page"=>1]) }}">'
                        . '       
                        ' . ($this->formatColumnName($name))
                        . '
                    </a>'
                        . ' 
                    @if($orderBy==\'' . $column->Field . '\') 
                        @if($order==\'asc\') 
                            <span class="fa fa-chevron-up"></span> 
                        @else 
                            <span class="fa fa-chevron-down"></span> 
                        @endif 
                    @endif '
                        . '
                </th>' . PHP_EOL;
                }
            }
        }
        return $f;
    }



    public function getViewIndexTable($columnsInfo, $tableName, $childOf)
    {

        $f = "";


        foreach ($columnsInfo as $column) {


            if($column->Field!="deleted_at") {


                $columnIsNumber = $this->columnIsNumeric($column);
                $columnIsBool = $this->columnIsBool($column);
                $columnIsString = $this->columnIsString($column);
                $alignText = "";



                $isFK = false;
                $parentField = "";
                $parentTable= "";
                if(!empty($childOf)) {
                    $parents = explode(',', $childOf);
                    foreach ($parents as $parent) {
                        if(str_singular($parent) . '_id' == $column->Field) {
                            $isFK = true;
                            $parentField = str_singular($parent);
                            $parentTable = $parent;
                        }
                    }
                }




                $isFileUpload = false;
                if (!is_null($this->upload)) {
                    if (in_array($column->Field, explode(',', $this->upload))) {
                        $isFileUpload = true;
                    }
                }




                if($isFK) {

                    $showParentField = "id";
                    // try to guess name of the "name" field
                    $dbFieldsParent = $this->schema[$parentTable];
                    foreach ($dbFieldsParent as $dc) {
                        // get first string column
                        if (strpos($dc->Type, 'varchar') !== false) {
                            $showParentField = $dc->Field;
                            break;
                        }
                    }


                    $c = '@if(isset($item->' . $parentField . '->' . $showParentField . ')) {{ $item->' . $parentField . '->' . $showParentField . ' }} @endif';


                } else if($isFileUpload) {
                    $c = '
                    @if(isset($item->' . $column->Field . '))
                        <a href="/UPLOAD_FOLDER/{{ $item->' . $column->Field . ' }}" target="_blank">
                            <img src="/UPLOAD_FOLDER/{{ $item->' . $column->Field . ' }}" width="100px">
                        </a>
                    @endif';


                } else if($column->Field=="created_at" || $column->Field=="updated_at") {
                    $c = '{{ \Carbon\Carbon::parse($item->' . $column->Field . ")->diffForHumans() }}";
                } else if($column->Type=="datetime" || $column->Type=="date" || $column->Type=="timestamp") {
                    $c = '{{ $item->' . $column->Field.' }}';
                } else if($columnIsNumber) {
                    $alignText = ' align="right"';
                    $c = '{{ $item->' . $column->Field . ' }}';
                } else if ($columnIsBool) {
                    $alignText = ' align="center"';



    //                    $c = PHP_EOL.'
    //                        <form class="form-inline" method="POST" action="{{  route(\''.$this->getRouteResourceName($tableName).'.update\', [$item->id]) }}">
    //                            {{ csrf_field() }}
    //                           <input name="_method" type="hidden" value="PUT">
    //                           <button  type="submit" class="btn btn-link btn-xs {{ ($item->' . $column->Field . '?"btn-success":"btn-danger") }}">
    //                               {{ ($item->' . $column->Field . '?"Yes":"No") }}
    //                           </button>
    //                       </form>'.PHP_EOL;


                    $c = '{{ ($item->' . $column->Field.' ? "Yes" : "No" ) }}';

                } else if($column->Type=="text" || $column->Type=="longtext" || $column->Type=="binary" || $column->Type=="varbinary") {
                    $c = '{{ $item->' . $column->Field . ' }}';

                } else if ($columnIsString) {
                    $c = '{{ str_limit($item->'.$column->Field.', $limit = 32, $end = "...") }}';

                } else {
                    $c = '{{ $item->' . $column->Field . ' }}';
                }


                if ($column->Type == "text" || $column->Type == "longtext" || $column->Type == "binary" || $column->Type == "varbinary") {
                    // do noy show big text columns, show comment
                    $f .= PHP_EOL.str_repeat(" ", 4*4).'{{--';
                }


                $f .= PHP_EOL.str_repeat(" ", 4*4).'<td'.($alignText).'> ';
                $f .= PHP_EOL.str_repeat(" ", 5*4).$c;
                $f .= PHP_EOL.str_repeat(" ", 4*4).'</td>';


                if ($column->Type == "text" || $column->Type == "longtext" || $column->Type == "binary" || $column->Type == "varbinary") {
                    // do noy show big text columns, show comment
                    $f .= PHP_EOL.str_repeat(" ", 4*4).'--}}';
                }
            }
        }
        return $f;

    }









    function columnIsNumeric($c)
    {
        $typeParts = explode('(', $c->Type);
        $size = 0;
        if(count($typeParts)>1) {
            $size = str_replace(')','',$typeParts[1]);
        }

        if($typeParts[0]=="int" || ($typeParts[0]=="tinyint" && $size>1) || $typeParts[0]=="smallint" || $typeParts[0]=="mediumint"
                 || $typeParts[0]=="bigint" || $typeParts[0]=="decimal" || $typeParts[0]=="float" || $typeParts[0]=="double" || $typeParts[0]=="real") {
            return true;
        }
        return false;
    }

    function columnIsDate($c)
    {
        if($c->Type=="datetime" || $c->Type=="date" || $c->Type=="timestamp") {
            return true;
        }
        return false;
    }


    function getColumnsDates($dbFields)
    {
        $list = array();
        foreach ($dbFields as $c) {
            if($c->Type=="datetime" || $c->Type=="date" || $c->Type=="timestamp") {
                $list[] = $c->Field;
            }
        }
        return $list;
    }



    function columnIsBool($c)
    {
        $typeParts = explode('(', $c->Type);
        $size = 0;
        if(count($typeParts)>1) {
            $size = str_replace(')','',$typeParts[1]);
        }


        if(($typeParts[0]=="tinyint" && $size==1) || $typeParts[0]=="boolean") {
            return true;
        }
        return false;
    }




    function columnIsString($c)
    {
        $typeParts = explode('(', $c->Type);
        $size = 0;
        if(count($typeParts)>1) {
            $size = str_replace(')','',$typeParts[1]);
        }
        if($typeParts[0]=="char" || $typeParts[0]=="varchar" || $typeParts[0]=="tinytext" || $typeParts[0]=="text"
            || $typeParts[0]=="mediumtext" || $typeParts[0]=="longtext" || $typeParts[0]=="binary" || $typeParts[0]=="varbinary"
            || $typeParts[0]=="tinyblob" || $typeParts[0]=="mediumblob" || $typeParts[0]=="blob" || $typeParts[0]=="longblob") {
            return true;
        }
        return false;
    }




    public function formatColumnName($name)
    {
        return ucfirst(str_replace('_', ' ', $name));
    }



    public function getViewForms($dbFields, $childOf, $manyWith, $editForm=false)
    {

        $pk = $this->getPrimaryKey($dbFields);

        $f = "";
        $counter = 0;
        foreach ($dbFields as $c) {
            $counter++;
            $fieldName = $c->Field;

            $formatedColumnName = $this->formatColumnName($c->Field);



            $isFK = false;
            $parentField = "";
            $parentTable= "";
            if(!empty($childOf)) {
                $parents = explode(',', $childOf);
                foreach ($parents as $parent) {
                    if(str_singular($parent) . '_id' == $c->Field) {
                        $isFK = true;
                        $parentField = str_singular($parent);
                        $parentTable = $parent;
                        $formatedColumnName = $this->formatColumnName($parentField);
                    }
                }
            }










            $typeParts = explode('(', $c->Type);
            $size = 0;
            if(count($typeParts)>1) {
                $size = str_replace(')','',$typeParts[1]);
            }
            $type = $typeParts[0];


            $highlightValidationError = ' @if ($errors->has("'.$fieldName.'")) class="has-error" @endif';


            if($fieldName!="created_at" && $fieldName!="updated_at" && $fieldName!="deleted_at" && $fieldName!=$pk
                    && $type!="varbinary" && $type!="binary") {

                $f .= str_repeat(" ", 3 * 4) . '<tr>' . PHP_EOL;
                // TD name
                $f .= str_repeat(" ", 4 * 4) . '<td>' . PHP_EOL;
                $f .= str_repeat(" ", 5 * 4) . $formatedColumnName . PHP_EOL;


                $f .= str_repeat(" ", 4 * 4) . '</td>' . PHP_EOL;

                // TD input
                $f .= str_repeat(" ", 4 * 4) . '<td>' . PHP_EOL;


                if ($editForm) {
                    $value = '{{ $errors->any() ? old(\'' . $fieldName . '\') : $item->' . $fieldName . ' }}';
                } else {
                    $value = '{{ old(\'' . $fieldName . '\') }}';
                }

                $inputSize = "300"; // default
                if ($this->columnIsNumeric($c)) {
                    $inputSize = "150";
                } else if ($this->columnIsDate($c)) {
                    $inputSize = "150";
                }


                $htmlChecked = '';
                if ($editForm) {
                    $htmlChecked .= '{{ $item->' . $fieldName . '?"checked":"" }}';
                }

                $htmlChecked .= '{{ old("' . $fieldName . '") ? "checked" : "" }}';



                $isFileUpload = false;
                if (!is_null($this->upload)) {
                    if (in_array($c->Field, explode(',', $this->upload))) {
                        $isFileUpload = true;
                    }
                }


                if ($isFK) {

                    $showParentField = "id";
                    // try to guess name of the "name" field
                    $dbFieldsParent = $this->schema[$parentTable];
                    foreach ($dbFieldsParent as $dc) {
                        // get first string column
                        if (strpos($dc->Type, 'varchar') !== false) {
                            $showParentField = $dc->Field;
                            break;
                        }
                    }


                    if ($editForm) {
                        // edit item form
                        $formElement = '
                            <select name="' . $c->Field . '" class="form-control">
                                <option value="">---</option>
                                @foreach(\App\Models\\' . $this->getModelName($parentTable) . '::get() as $parentItem)
                                    <option value="{{ $parentItem->id }}"
                                            @if(old("' . $c->Field . '") == $parentItem->id)
                                                selected
                                                
                                            @elseif(!$errors->any() && $parentItem->id==$item->' . $c->Field . ')
                                                selected
                                                
                                            @endif>
                                        {{ $parentItem->name }}
                                    </option>
                                @endforeach
                            </select>
                        ';


                    } else {
                        // add item form

                        $formElement = '
                             <select name="' . $c->Field . '" class="form-control">
                                <option value="">---</option>
                                @foreach(\App\Models\\' . $this->getModelName($parentTable) . '::get() as $parentItem)
                                    <option value="{{ $parentItem->id }}"
                                            @if(old("' . $c->Field . '") == $parentItem->id)
                                                selected
                                            @endif>
                                        {{ $parentItem->name }}
                                    </option>
                                @endforeach
                            </select>
                        ';
                    }


                } else if ($isFileUpload) {

                    $formElement  = "";

                    if ($editForm) {

                        $formElement .=  '<a href="/UPLOAD_FOLDER/{{ $item->' . $fieldName . ' }}" target="_blank">{{ $item->' . $fieldName . ' }}</a>'
                            . '<br>'. PHP_EOL . str_repeat(" ", 6*4);
                    }

                    $formElement .= '<input type="file" class="form-control input-sm" name="' . $fieldName . '">';


                } else if($type=="text") {


                    // $f .= is_null($c->Default) ? "null" :  $c->Default;



                    $formElement = '<textarea style="width: 600px" rows="5" class="form-control input-sm" name="' . $fieldName . '">' . $value . '</textarea>';


                } else if($this->columnIsBool($c)) {
                    $formElement = '<input type="hidden" value="0" name="' . $fieldName . '">';
                    $formElement .= PHP_EOL . str_repeat(" ", 6*4) . '<input type="checkbox" class="form-control input-sm" '.$htmlChecked.' name="' . $fieldName . '" value="1">';
//                    $formElement .= ' '.$formatedColumnName.'</label>';

                } else {
                    $formElement = '<input style="width: '.$inputSize.'px" class="form-control input-sm" name="' . $fieldName . '" value="' . $value . '">';
                }


                $f .= str_repeat(" ", 5*4).'<div' . $highlightValidationError . '>
                        '.$formElement.'
                    </div>'.PHP_EOL;




                $f .= str_repeat(" ", 4*4) . '</td>'.PHP_EOL;





                $f .= str_repeat(" ", 3*4) . '</tr>'.PHP_EOL;
            }

        }





        $js = false;
        if(!empty($manyWith)) {
            $manyWithParts = explode(',', $manyWith);
            foreach ($manyWithParts as $mw) {


                $f .= '<td>' . $this->formatColumnName($mw) . '</td><td>';

                if(!$js) {
                    $js = true;
                    $f .= '
                        <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
                    ';
                }



                $f .= '
                
                        <select name="'.$mw.'[]" id="select_'.$mw.'" style="width:600px"
                                class="form-control js-example-basic-multiple" multiple="multiple">
                            @foreach(\App\Models\\'.$this->getModelName($mw).'::get() as $itemOfMany)
                                <option
                                        @if($errors->any())
                                            @if(in_array($itemOfMany->id, old(\''.$mw.'\',[]))) selected @endif';

                if ($editForm) {
                    $f .= '
                                @else
                                    @foreach($item->' . $mw . '()->get() as $x)
                                        @if($x->id==$itemOfMany->id)
                                            selected
                                        @endif
                                    @endforeach';
                }

                $f .= ' 
                                        @endif
                                        value="{{ $itemOfMany->id }}">{{ $itemOfMany->name }}</option>
                            @endforeach
                        </select>
                       
                       

                    <script type="text/javascript">
                        $("#select_'.$mw.'").select2();
                    </script>
                                        
                    
                    
                ';



                $f .= '</td>';
            }
        }






        return $f;

    }



    public function getViewShowContent($dbFields, $editForm=false)
    {
        $f = "";
        $counter = 0;
        foreach ($dbFields as $c) {
            $counter++;
            $fieldName = $c->Field;

            $formatedColumnName = $this->formatColumnName($c->Field);

            $typeParts = explode('(', $c->Type);

            $type = $typeParts[0];

            $f .= str_repeat(" ", 3*4).'<tr>'.PHP_EOL;
            $f .= str_repeat(" ", 4*4) . '<td>' . PHP_EOL;
            $f .= str_repeat(" ", 5*4) . $formatedColumnName . PHP_EOL;
            $f .= str_repeat(" ", 4*4) . '</td>' . PHP_EOL;
            $f .= str_repeat(" ", 4*4).'<td>'.PHP_EOL;


            $value = '{{ $item->'.$fieldName.' }}';


            if($type=="text") {

                $formElement = '' . $value . '';

            } else if($this->columnIsBool($c)) {

                $formElement = '{{ $item->'.$fieldName.' ? "Yes" : "No" }}';


            } else {
                $formElement = '' . $value . '';
            }

            $f .= str_repeat(" ", 5*4).''.$formElement.''.PHP_EOL;
            $f .= str_repeat(" ", 4*4) . '</td>'.PHP_EOL;
            $f .= str_repeat(" ", 3*4) . '</tr>'.PHP_EOL;

        }
        return $f;

    }




    public function createRoutes($tableName, $childOf, $crud)
    {

        $routes = "";




        $tableIsChild = !empty($childOf);

        $routePath = base_path('routes/web.php');
        if(! \File::exists($routePath) && false) { // DISABLED
            $this->error("Route file ".$routePath." not found.");
        } else {
            // create routes
            $newRoutePath = $this->getRouteResourceName($tableName);
            if($tableIsChild && false) { // DISABLED
                $newRoutePath = $childOf . '/{parentId}/'.$newRoutePath;
            }
            $routeText = "Route::resource('" . $newRoutePath . "', 'Crud\\" . $this->getControllerName($tableName) . "', array('only' => array('index', 'create', 'store', 'edit', 'show', 'update', 'destroy')));";


            // DISABLED

            /*

            $comment = "// ";
            if( strpos( $crud, "r" ) !== false ) {
                // read is on
                $comment = "";
            }
            $routeText .= PHP_EOL.$comment."Route::get('/".$tableName."', 'Crud\\".$this->getControllerName($tableName)."@index')->name('".$tableName.".index');";
            $routeText .= PHP_EOL.$comment."Route::get('/".$tableName."/{id}', 'Crud\\".$this->getControllerName($tableName)."@show')->name('".$tableName.".show');";

			
			
            $comment = "// ";
            if( strpos( $crud, "c" ) !== false ) {
                // create is on
                $comment = "";
            }
            $routeText .= PHP_EOL.$comment."Route::get('/".$tableName."/create', 'Crud\\".$this->getControllerName($tableName)."@create')->name('".$tableName.".create');";
            $routeText .= PHP_EOL.$comment."Route::put('/".$tableName."/{id}', 'Crud\\".$this->getControllerName($tableName)."@update')->name('".$tableName.".update');";


            $comment = "// ";
            if( strpos( $crud, "u" ) !== false ) {
                // update is on
                $comment = "";

            }
            $routeText .= PHP_EOL.$comment."Route::get('/".$tableName."/{id}/edit', 'Crud\\".$this->getControllerName($tableName)."@edit')->name('".$tableName.".edit');";
            $routeText .= PHP_EOL.$comment."Route::post('/".$tableName."', 'Crud\\".$this->getControllerName($tableName)."@store')->name('".$tableName.".store');";


            $comment = "// ";
            if( strpos( $crud, "d" ) !== false ) {
                // delete is on
                $comment = "";
            }
            $routeText .= PHP_EOL.$comment."Route::delete('/".$tableName."/{id}', 'Crud\\".$this->getControllerName($tableName)."@destroy')->name('".$tableName.".destroy');";



            $routeCollection = \Illuminate\Support\Facades\Route::getRoutes();
            $routeAlreadyExists = true;


            $this->info(json_encode($routeCollection));

            */


            // foreach ($routeCollection as $r) {
            //     $rPath = $r->getPath();
            //     // $value->getName()
            //     if($rPath==$newRoutePath) {
            //         $routeAlreadyExists = true;
            //     }
            // }



            if(true) { // DISABLED
                // $this->warn(PHP_EOL . "Route path '". $newRoutePath. "' already exists, please add to your route: ");
                $this->warn(PHP_EOL . "Please add to your routes:");
                $this->warn("---------------------------------------------------------------------");
                $this->warn($routeText);
                $this->warn("---------------------------------------------------------------------");

            } else {
                \File::append($routePath, PHP_EOL.PHP_EOL.$routeText.PHP_EOL.PHP_EOL);
            }
        }




    }



    public function createController($tableName, $dbFields, $tableIsChild)
    {
        $pk = $this->getPrimaryKey($dbFields);

        $controllerTemplate = \File::get(($this->templatePath . 'controllers/controller.txt'));

        $controllerName = $this->getControllerName($tableName);
        $controllerFileName = $controllerName.".php";
        $viewFolder = $this->getViewFolder($tableName);


        // region generate this controller using the template and database structure

        $controllerTemplate = str_replace('$NAME$', $controllerName, $controllerTemplate);
        $modelName = $this->getModelName($tableName);
        $controllerTemplate = str_replace('$MODEL$', $modelName, $controllerTemplate);
        $controllerTemplate = str_replace('$VIEW_FOLDER$', $viewFolder, $controllerTemplate);

        $validation = $this->getValidationRules($dbFields);
        $controllerTemplate = str_replace('$VALIDATION$', $validation, $controllerTemplate);

        $controllerTemplate = str_replace('$NULL_FILEDS$', "", $controllerTemplate);


        if($tableIsChild && false) { // DISABLED
            // remove markes
            $controllerTemplate = str_replace('$IF_HAS_PARENT$', "", $controllerTemplate);
            $controllerTemplate = str_replace('$END_HAS_PARENT$', "", $controllerTemplate);
        } else {
            // remove markers and text
            $controllerTemplate = preg_replace('~\$IF_HAS_PARENT\$.*?\$END_HAS_PARENT\$~ims', '', $controllerTemplate);
        }

        $controllerTemplate = str_replace('$PRIMARY_KEY$', $pk, $controllerTemplate);




        $fileUpload = "";

        if(!is_null($this->upload)) {
            foreach (explode(',', $this->upload) as $fu) {
                $fileUpload .= '
        if (request()->hasFile("'.$fu.'")) {
            if (request()->file("'.$fu.'")->isValid()) {
                $item->'.$fu.' = md5(uniqid(rand(), true)) . "." . request()->file("'.$fu.'")->getClientOriginalExtension();
                request()->file("'.$fu.'")->move(public_path() . "/UPLOAD_FOLDER/", $item->'.$fu.');
            } else {    
                return redirect()->back()->withInput(request()->input())->withErrors("Uploaded file is not valid.");
            }
        }';
            }
        }



        $controllerTemplate = str_replace('$FILE_UPLOAD$', $fileUpload,  $controllerTemplate);





        $setEmptyAsNull = "";
        $nullFields =  $this->getNullOrDefaultColumns($dbFields);
        foreach($nullFields as $nullField) {
            $setEmptyAsNull .= '        $input[\''. $nullField .'\'] = $input[\''. $nullField .'\']=="" ? null : $input[\''. $nullField .'\'];' . PHP_EOL;
        }
        $controllerTemplate = str_replace('$SET_EMPTY_AS_NULL$', $setEmptyAsNull, $controllerTemplate);





        // file upload --- ON_DESTROY_DELETE_FILES
        $onDestroyDeleteFiles = "";
        if(isset($this->upload)) {
            foreach(explode(',', $this->upload)as $fu) {
                $onDestroyDeleteFiles .= '
        if(\File::exists(public_path() . "/UPLOAD_FOLDER/" . $item->' . trim($fu).') && !empty($item->' . trim($fu).')) {
            unlink(public_path() . "/UPLOAD_FOLDER/" . $item->' . trim($fu).');
        }'.PHP_EOL;
            }
        }
        $controllerTemplate = str_replace('$ON_DESTROY_DELETE_FILES$', $onDestroyDeleteFiles, $controllerTemplate);






        // $SET_MANY_TO_MANY$
        $setManyToMany = "";

        if(isset($this->manyWith)) {
            $setManyToMany = "// save many to many";
            foreach(explode(',', $this->manyWith) as $mw) {
                $setManyToMany .= '
        $item->'.trim($mw).'()->sync(request()->input(\''.trim($mw).'\', []));
                ';
            }
        }
        $controllerTemplate = str_replace('$SET_MANY_TO_MANY$', $setManyToMany, $controllerTemplate);








        // endregion


        // region  write generated model to disk
        $controllerFolderPath = app_path('Http/Controllers/Crud');
        $controllerPath = $controllerFolderPath . '/'.$controllerFileName;

        if(!\File::exists($controllerFolderPath)) {
            \File::makeDirectory($controllerFolderPath, 0755, true);
        }

        if(\File::exists($controllerPath) && !$this->overwriteExistingFiles) {
            $this->warn('Controller '.$controllerPath.' already exists, it is not overwritten.');
        } else {
            $bytesWritten = \File::put($controllerPath, $controllerTemplate);
            if ($bytesWritten === false)
            {
                $this->error('Error writing to file'.$controllerPath);
            }

        }
        #endregion

    }



    private function getNullOrDefaultColumns($dbFields) {

        $nullColumns = array();
        $pk = $this->getPrimaryKey($dbFields);
        foreach ($dbFields as $fld) {
            $fieldName = $fld->Field;

            // file upload fields are not set to empty (because of <input type="file"...)
            $isFileUpload = false;
            if (!is_null($this->upload)) {
                if (in_array($fieldName, explode(',', $this->upload))) {
                    $isFileUpload = true;
                }
            }


            if ($fieldName != $pk && $fieldName != "created_at" && $fieldName != "updated_at" && $fieldName != "deleted_at" && !$isFileUpload) {
                if ($fld->Null == "YES" || !is_null($fld->Default)) {
                    $nullColumns[] = $fieldName;
                }
            }
        }
        return $nullColumns;
    }









    private function getControllerName($tableName)
    {
        return ucwords((camel_case($tableName))) . 'Controller';
    }



    private function getModelName($tableName)
    {
        return ucwords(str_singular(camel_case($tableName)));
    }



    private function getViewFolder($tableName)
    {
        return $tableName;
    }



    private function getRouteResourceName($tableName)
    {
        return $tableName;
    }


    public function createModel($tableName, $dbFields, $parentOf, $childOf, $manyWith)
    {
        $pk = $this->getPrimaryKey($dbFields);



        $columns = array();
        foreach ($dbFields as $field) {
            $columns[] = $field->Field;
        }


        $modelTemplate = \File::get($this->templatePath . 'models/model.txt');

        $modelName = $this->getModelName($tableName);

        // region generate this model using the template and database structure
        $dates = "";

        if(array_search('deleted_at', $columns)) {
            $modelTemplate = str_replace('$COMMENTS_USE_DELETES$', '', $modelTemplate);
        } else {
            $modelTemplate = str_replace('$COMMENTS_USE_DELETES$', '// ', $modelTemplate);
        }

        if(array_search('created_at', $columns)) {
            $modelTemplate = str_replace('$TIMESTAMPS$', "true", $modelTemplate);
        } else {
            $modelTemplate = str_replace('$TIMESTAMPS$', "false", $modelTemplate);
        }

        $columnsDates = $this->getColumnsDates($dbFields);
        foreach ($columnsDates as $dateC) {
            $dates .= ",".PHP_EOL."        '" . $dateC . "'";
        }

        $modelTemplate = str_replace('$DATES$', ltrim($dates, ', '), $modelTemplate);

        $fillable = "";
        foreach ($columns as $dbField) {
            if($dbField!=$pk && $dbField!="created_at" && $dbField!="updated_at" && $dbField!="deleted_at") {
                $fillable .= ",".PHP_EOL."        '" . $dbField . "'";
            }
        }
        $modelTemplate = str_replace('$FILLABLE$', ltrim($fillable, ', '), $modelTemplate);


        $modelTemplate = str_replace('$TABLE$', $tableName, $modelTemplate);
        $modelTemplate = str_replace('$NAME$', $modelName, $modelTemplate);

        // table name si plural form of model? (ends in "s"?)
        if (substr($tableName, -1) == 's') {
            // do nothing, comment custom table name
            $modelTemplate = str_replace('$COMMENT_TABLE_NAME$', "//", $modelTemplate);
        } else {
            // use custom table name
            $modelTemplate = str_replace('$COMMENT_TABLE_NAME$', "", $modelTemplate);
        }




        $modelTemplate = str_replace('?PRIMARY_KEY?', $pk, $modelTemplate);
        if($pk=="id") {
            $modelTemplate = str_replace('$COMMENT_PRIMARY_KEY$', '// ', $modelTemplate);
        } else {
            $modelTemplate = str_replace('$COMMENT_PRIMARY_KEY$', '', $modelTemplate);
        }


        // region $BELONGS_TO_MANY$
        $belongsToMany = "";
        if(!empty($manyWith)) {
            $mwList = explode(',', $manyWith);
            foreach ($mwList as $mw) {

                $fk = str_singular($tableName).'_id';

                $localKey = $this->getPrimaryKey($dbFields);

                $belongsToMany .= '    
    public function ' . $mw.'()
    {
        return $this->belongsToMany(\'App\Models\\'.$this->getModelName($mw).'\');
        // example:   function products() ...  return $this->belongsToMany(\'App\Products\', \'products_shops\', \'shops_id\', \'products_id\');
    }
    
    ';
            }
        }


        // endregion





        $hasMany = "";
        if(!empty($parentOf)) {
            $parents = explode(',', $parentOf);
            foreach ($parents as $parent) {
                $parentParts = explode(',', $parent);
                $fk = str_singular($tableName).'_id';
                if(count($parentParts)>1) {
                    $fk = $parentParts[1];
                }

                $localKey = $this->getPrimaryKey($dbFields);

                $hasMany .= '    
    public function '.$parentParts[0].'()
    {
        return $this->hasMany(\'App\Models\\'.$this->getModelName($parentParts[0]).'\', "'.$fk.'", "'.$localKey.'");
    }
    
    ';
            }
        }



        $belongsTo = "";
       if(!empty($childOf)) {
           $parents = explode(',', $childOf);
           foreach ($parents as $parent) {

               $fk = str_singular($parent).'_id';



   //             $parentParts = explode(',', $parent);
   //
   //             if(count($parentParts)>1) {
   //                 $fk = $parentParts[1];
   //             }
   //
   //             $localKey = $this->getPrimaryKey($dbFields);
   //
               $belongsTo .= '
   public function '.str_singular($parent).'()
   {
       return $this->belongsTo(\'App\Models\\'.$this->getModelName($parent).'\', "'.$fk.'", "'.$this->getPrimaryKey($dbFields).'");
   }
   
   ';
           }
       }








        $modelTemplate = str_replace('$HASMANY$', $hasMany, $modelTemplate);
        $modelTemplate = str_replace('$BELONGSTO$', $belongsTo, $modelTemplate);
        $modelTemplate = str_replace('$BELONGS_TO_MANY$', $belongsToMany, $modelTemplate);



        // endregion








        // region  write generated model to disk
        $modelsPath = app_path('Models');
        if(!\File::exists($modelsPath)) {
            \File::makeDirectory($modelsPath, 0755, true);
        }
        $modelPath = $modelsPath .'/'. $modelName.".php";
        if(\File::exists($modelPath) && !$this->overwriteExistingFiles) {
            $this->warn('Model '.$modelPath.' already exists, it is not overwritten.');
        } else {
            $bytesWritten = \File::put($modelPath, $modelTemplate);
            if ($bytesWritten === false)
            {
                $this->error('Error writing to file'.$modelPath);
            }

        }
        #endregion

    }






    private function getPrimaryKey($dbFields)
    {
        foreach ($dbFields as $field) {
            if($field->Key == "PRI") {
                return $field->Field;
            }
        }
        return "id";
    }







}
