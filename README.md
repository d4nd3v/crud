# Laravel 5 CRUD Generator

## Usage

### Step 1: Install Through Composer

```
composer require d4nd3v/crud
```

### Step 2: Add the Service Provider

Add the provider in `app/Providers/AppServiceProvider.php`

```php
public function register()
{
    ...
    $this->app->register('D4nd3v\Crud\CrudServiceProvider');
}
```

### Step 3: Run Artisan!

Run `php artisan generate:crud TABLE_NAME` from the console.

|Options                           |Description                 |
|:---------------------------------|:---------------------------|
|--overwrite=false                 | overwrite existing files   |
|--crud=CRUD                       | C(reate) R(read) U(pdate) D(elete)   |
|--model_only=false                | only generates model file  |
|--parent_of=TABLE1,TABLE2,TABLE3  | add links & "belongs to" in model   |
|--child_of=TABLE4,TABLE5,TABLE6   | generates 'parent/pid/resource/rid' |
  
exemple: --crud=CRU (all actions without delete)  

## Notes
If you need pagination don't forget to run:  
`php artisan vendor:publish --tag=laravel-pagination`

The generated files are:
- app/Http/Controllers/Crud/[Resource]Controller.php
- app/Models/[Resource].php
- resources/views/[Resource]/create.blade.php
- resources/views/[Resource]/edit.blade.php
- resources/views/[Resource]/index.blade.php
- \+ added route resource in routes/web.php



