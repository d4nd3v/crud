<?php

namespace D4nd3v\Crud;

use Illuminate\Console\Command;
use League\Flysystem\Directory;

class CRUDCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:crud {table} {--model_only=false} {--child_of=} {--parent_of=} {--overwrite=false}';

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



    public function handle()
    {
        $tableName = $this->argument('table');
        $modelOnly = $this->option('model_only');
        $parentOf = $this->option('parent_of'); // table_1.fk1,table_2.fk2,table_3.fk3
        $childOf = $this->option('child_of'); // TABLE

        if($this->option('overwrite')=="true") {
            $this->overwriteExistingFiles =  true;
        }

        $dbFields = \DB::select(\DB::raw("SHOW COLUMNS FROM ".$tableName.""));
        $this->createModel($tableName, $dbFields, $parentOf, $childOf);
        if($modelOnly!=="true") {
            $this->createRoutes($tableName, $childOf);
            $this->createController($tableName, $dbFields, !empty($childOf));
            $this->createViews($tableName, $dbFields, !empty($childOf), $parentOf);
        }

        $this->info("Done.");

    }



    public function createViews($tableName, $dbFields, $tableIsChild, $parentOf)
    {
        // create view folder
        $viewsPath = resource_path('views') .'/'. $this->getViewFolder($tableName);
        if(!\File::exists($viewsPath)) {
            \File::makeDirectory($viewsPath, 0775, true);
        }
        $this->createCreateView($viewsPath, $tableName, $dbFields, $tableIsChild, $parentOf);
        $this->createEditView($viewsPath, $tableName, $dbFields, $tableIsChild, $parentOf);
        $this->createIndexView($viewsPath, $tableName, $dbFields, $tableIsChild, $parentOf);
    }


    public function createCreateView($viewsPath, $tableName, $dbFields, $tableIsChild, $parentOf)
    {
        $pk = $this->getPrimaryKey($dbFields);
        $createViewTemplate = \File::get(($this->templatePath . 'create_blade.txt'));
        $viewCreatePath = $viewsPath. '/create.blade.php';
        $newRoutePath = $this->getRouteResourceName($tableName);
        $createViewTemplate = str_replace('$ROUTE$', $newRoutePath, $createViewTemplate);
        $createViewTemplate = str_replace('$FORM$', $this->getViewForms($dbFields), $createViewTemplate);
        $createViewTemplate = $this->showHideParentInTemplate($createViewTemplate, $tableIsChild);
        $createViewTemplate = str_replace('$PRIMARY_KEY$', $pk, $createViewTemplate);

        $masterLayout = $this->getMasterLayout();
        if($masterLayout!="") {
            $createViewTemplate = str_replace('$MASTER_LAYOUT$', '@extends(\''.$masterLayout.'\')', $createViewTemplate);
        }

        if(!\File::exists($viewCreatePath) || $this->overwriteExistingFiles) {
            \File::put($viewCreatePath, $createViewTemplate);
        }
    }


    public function createEditView($viewsPath, $tableName, $dbFields, $tableIsChild, $parentOf)
    {
        $pk = $this->getPrimaryKey($dbFields);
        $editViewTemplate = \File::get(($this->templatePath . 'edit_blade.txt'));
        $viewEditPath = $viewsPath. '/edit.blade.php';
        $newRoutePath = $this->getRouteResourceName($tableName);
        $editViewTemplate = str_replace('$ROUTE$', $newRoutePath, $editViewTemplate);
        $editViewTemplate = str_replace('$FORM$', $this->getViewForms($dbFields, true), $editViewTemplate);
        $editViewTemplate = $this->showHideParentInTemplate($editViewTemplate, $tableIsChild);
        $editViewTemplate = str_replace('$PRIMARY_KEY$', $pk, $editViewTemplate);

        $masterLayout = $this->getMasterLayout();
        if($masterLayout!="") {
            $editViewTemplate = str_replace('$MASTER_LAYOUT$', '@extends(\''.$masterLayout.'\')', $editViewTemplate);
        }

        if(!\File::exists($viewEditPath) || $this->overwriteExistingFiles) {
            \File::put($viewEditPath, $editViewTemplate);
        }
    }


    public function createIndexView($viewsPath, $tableName, $dbFields, $tableIsChild, $parentOf)
    {
        $pk = $this->getPrimaryKey($dbFields);
        $newRoutePath = $this->getRouteResourceName($tableName);
        $indexViewTemplate = \File::get(($this->templatePath . 'index_blade.txt'));
        $viewIndexPath = $viewsPath. '/index.blade.php';
        $indexViewTemplate = str_replace('$ROUTE$', $newRoutePath, $indexViewTemplate);
        $indexViewTemplate = str_replace('$TABLE$', $this->getViewIndexTable($dbFields, $tableName), $indexViewTemplate);
        $indexViewTemplate = str_replace('$TABLE_HEADER$', $this->getViewIndexTableHeader($dbFields), $indexViewTemplate);
        $indexViewTemplate = $this->showHideParentInTemplate($indexViewTemplate, $tableIsChild);
        $childLinkHeader = "";
        $childLink = "";
        if(!empty($parentOf)) {
            $parentOfArray = explode(',', $parentOf);
            foreach ($parentOfArray as $pof) {
                $childLinkHeader .= PHP_EOL . '<th>&nbsp;</th>';
                $childLink .= PHP_EOL . '<td><a href="/'.$this->getRouteResourceName($tableName).'/{{ $item->'.$pk.' }}/'.$pof.'">'.$pof.'</a></td>';
            }
            // $indexViewTemplate = str_replace('$CHILD_LINK$', '<td width="1"><a href="{{ route(\''.$childTable.'.index\', [$item->id]) }}">'.$childTable.'</a></td>', $indexViewTemplate);
        }
        $indexViewTemplate = str_replace('$CHILD_LINK_HEADER$', $childLinkHeader, $indexViewTemplate);
        $indexViewTemplate = str_replace('$CHILD_LINK$', $childLink, $indexViewTemplate);
        $indexViewTemplate = str_replace('$PRIMARY_KEY$', $pk, $indexViewTemplate);

        $masterLayout = $this->getMasterLayout();
        if($masterLayout!="") {
            $indexViewTemplate = str_replace('$MASTER_LAYOUT$', '@extends(\''.$masterLayout.'\')', $indexViewTemplate);
        }

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



    public function showHideParentInTemplate($template, $tableIsChild)
    {
        if ($tableIsChild) {
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
                if($fieldType=="varchar") {
                    $fieldRules[] = "max:".$size;
                }
                if($fieldType=="tinyint" || $fieldType=="int" || $fieldType=="smallint" || $fieldType=="mediumint" || $fieldType=="bigint") {
                    $fieldRules[] = "integer";
                }
                if($fieldType=="date" || $fieldType=="datetime" || $fieldType=="timestamp") {
                    $fieldRules[] = "date";
                }
                $v .= PHP_EOL . "        '" . $fieldName . "'" . ' => ' . '"'.(implode('|',$fieldRules)).'",';
            }
        }
        return $v;
    }



    public function getViewIndexTableHeader($columnsInfo)
    {
        $displayedFields = $this->getTableDisplayedFields($columnsInfo);
        $f = "";
        foreach ($displayedFields as $column) {
            $f .= str_repeat(" ", 3*4).'<th><a href="?order={{ $order==\'asc\'?"desc":"asc" }}&by='.$column->Field.'">'
                . ($this->formatColumnName($column->Field))
                . '</a>'
                . ' @if($order_by==\''.$column->Field.'\') @if($order==\'asc\') <span class="glyphicon glyphicon-menu-up"></span> @else <span class="glyphicon glyphicon-menu-down"></span> @endif @endif '
                . '</th>'
                . PHP_EOL;
        }
        return $f;
    }



    public function getViewIndexTable($columnsInfo, $tableName)
    {

        $displayedFields = $this->getTableDisplayedFields($columnsInfo);
        $f = "";
        foreach ($displayedFields as $column) {
            $columnIsNumber = $this->columnIsNumeric($column);
            $columnIsBool = $this->columnIsBool($column);
            $columnIsString = $this->columnIsString($column);
            $alignText = "";
            if($column->Field=="created_at" || $column->Field=="updated_at") {
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


                $c = '{{ ($item->' . $column->Field.'?"Yes":"No") }}';

            } else if ($columnIsString) {
                $c = '{{ str_limit($item->'.$column->Field.', $limit = 16, $end = "...") }}';

            } else {
                $c = '{{ $item->' . $column->Field . ' }}';
            }

            $f .= str_repeat(" ", 3*4).'<td'.($alignText).'> '.$c.' </td>'.PHP_EOL;
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
        $list = "";
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






    public function getTableDisplayedFields($columnsInfo)
    {
        $fields = array();
        foreach ($columnsInfo as $c) {
            $fieldName = $c->Field;

            $typeParts = explode('(', $c->Type);
            $size = 0;
            if(count($typeParts)>1) {
                $size = str_replace(')','',$typeParts[1]);
            }
            $type = $typeParts[0];

            // $fieldName!="created_at" && $fieldName!="updated_at" &&
            $inTable = true;
            if($fieldName=="deleted_at") {
                $inTable = false;
            } else if($type=="text" || $type=="longtext" || $type=="binary" || $type=="varbinary") {
                $inTable = false;
            }
            if($inTable) {
                $fields[] = $c;
            }
        }
        return $fields;
    }



    public function formatColumnName($name)
    {
        return ucfirst(str_replace('_', ' ', $name));
    }



    public function getViewForms($dbFields, $editForm=false)
    {

        $pk = $this->getPrimaryKey($dbFields);

        $f = "";
        foreach ($dbFields as $c) {
            $fieldName = $c->Field;

            $typeParts = explode('(', $c->Type);
            $size = 0;
            if(count($typeParts)>1) {
                $size = str_replace(')','',$typeParts[1]);
            }
            $type = $typeParts[0];


            $highlightValidationError = ' @if ($errors->has("'.$fieldName.'")) class="has-error" @endif';


            if($fieldName!="created_at" && $fieldName!="updated_at" && $fieldName!="deleted_at" && $fieldName!=$pk
                    && $type!="varbinary" && $type!="binary") {
                $f .= str_repeat(" ", 3*4).'<tr>'.PHP_EOL;
                // TD name
                $f .= str_repeat(" ", 4*4) . '<td>' . PHP_EOL;
                $f .= str_repeat(" ", 5*4) . $this->formatColumnName($c->Field) . PHP_EOL;
                $f .= str_repeat(" ", 4*4) . '</td>' . PHP_EOL;

                // TD input
                $f .= str_repeat(" ", 4*4).'<td>'.PHP_EOL;
                if($editForm) {
                    $value = '{{ old(\''.$fieldName.'\')?old(\''.$fieldName.'\'):$item->'.$fieldName.' }}';
                } else {
                    $value = '{{ old(\''.$fieldName.'\') }}';
                }

                $inputSize = "300"; // default
                if($this->columnIsNumeric($c)) {
                    $inputSize = "150";
                } else if ($this->columnIsDate($c)) {
                    $inputSize = "150";
                }


                $htmlChecked = '';
                if($editForm) {
                    $htmlChecked .= '{{ $item->'.$fieldName.'?"checked":"" }}';
                }

                $htmlChecked .= '{{ old("'.$fieldName.'") ? "checked" : "" }}';



                if($type=="text") {
                    $formElement = '<textarea style="width: 300px" rows="3" class="form-control input-sm" name="' . $fieldName . '">' . $value . '</textarea>';
                } else if($this->columnIsBool($c)) {
                    $formElement = '<input type="hidden" value="0" name="' . $fieldName . '">';
                    $formElement .= PHP_EOL . str_repeat(" ", 6*4) . '<input type="checkbox" class="form-control input-sm" '.$htmlChecked.' name="' . $fieldName . '" value="1">';
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
        return $f;

    }





    public function createRoutes($tableName, $childOf)
    {

        $tableIsChild = !empty($childOf);

        $routePath = base_path('routes/web.php');
        if(! \File::exists($routePath)) {
            $this->error("Route file ".$routePath." not found.");
        } else {
            // create routes
            $newRoutePath = $this->getRouteResourceName($tableName);
            if($tableIsChild) {
                $newRoutePath = $childOf . '/{parentId}/'.$newRoutePath;
            }
            $routeText = "Route::resource('" . $newRoutePath . "', '" . $this->getControllerName($tableName) . "', array('only' => array('index', 'create', 'store', 'edit', 'show', 'update', 'destroy')));";
            $routeCollection = \Route::getRoutes();
            $routeAlreadyExists = false;
            foreach ($routeCollection as $r) {
                $rPath = $r->getPath();
                // $value->getName()
                if($rPath==$newRoutePath) {
                    $routeAlreadyExists = true;
                }
            }

            if($routeAlreadyExists) {
                $this->warn(PHP_EOL . "Route path '". $newRoutePath. "' already exists, please add to your route: ");
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

        $controllerTemplate = \File::get(($this->templatePath . 'controller.txt'));

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


        if($tableIsChild) {
            // remove markes
            $controllerTemplate = str_replace('$IF_HAS_PARENT$', "", $controllerTemplate);
            $controllerTemplate = str_replace('$END_HAS_PARENT$', "", $controllerTemplate);
        } else {
            // remove markers and text
            $controllerTemplate = preg_replace('~\$IF_HAS_PARENT\$.*?\$END_HAS_PARENT\$~ims', '', $controllerTemplate);
        }

        $controllerTemplate = str_replace('$PRIMARY_KEY$', $pk, $controllerTemplate);


        $setEmptyAsNull = "";
        $nullFields =  $this->getNullOrDefaultColumns($dbFields);
        foreach($nullFields as $nullField) {
            $setEmptyAsNull .= '        $input[\''. $nullField .'\'] = $input[\''. $nullField .'\']=="" ? null : $input[\''. $nullField .'\'];' . PHP_EOL;
        }
        $controllerTemplate = str_replace('$SET_EMPTY_AS_NULL$', $setEmptyAsNull, $controllerTemplate);



        // endregion


        // region  write generated model to disk
        $controllerPath = app_path('Http/Controllers').'/'.$controllerFileName;
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
            if ($fieldName != $pk && $fieldName != "created_at" && $fieldName != "updated_at" && $fieldName != "deleted_at") {
                if ($fld->Null == "YES" || !is_null($fld->Default)) {
                    $nullColumns[] = $fieldName;
                }
            }
        }
        return $nullColumns;
    }









    private function getControllerName($tableName)
    {
        return ucwords((camel_case($tableName))) . 'CRUDController';
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


    public function createModel($tableName, $dbFields, $parentOf, $childOf)
    {
        $pk = $this->getPrimaryKey($dbFields);



        $columns = array();
        foreach ($dbFields as $field) {
            $columns[] = $field->Field;
        }


        $modelTemplate = \File::get($this->templatePath . 'model.txt');

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



        // TODO: implemnteaza
        $belongsTo = "";
//        if(!empty($childOf)) {
//            $parents = explode(',', $parentOf);
//            foreach ($parents as $parent) {
//                $parentParts = explode(',', $parent);
//                $fk = str_singular($tableName).'_id';
//                if(count($parentParts)>1) {
//                    $fk = $parentParts[1];
//                }
//
//                $localKey = $this->getPrimaryKey($dbFields);
//
//                $hasMany .= '
//    public function '.$parentParts[0].'()
//    {
//        return $this->hasMany(App\Models\\'.$this->getModelName($parentParts[0]).', "'.$fk.'", "'.$localKey.'");
//    }
//
//    ';
//            }
//        }








        $modelTemplate = str_replace('$HASMANY$', $hasMany, $modelTemplate);
        $modelTemplate = str_replace('$BELONGSTO$', $belongsTo, $modelTemplate);



        // endregion








        // region  write generated model to disk
        $modelsPath = app_path('Models');
        if(!\File::exists($modelsPath)) {
            \File::makeDirectory($modelsPath, 0775, true);
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
