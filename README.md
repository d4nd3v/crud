# Laravel 5 CRUD Resource Generator

## Usage

### Step 1: Install Through Composer

```
composer require d4nd3v/test2
```

### Step 2: Add the Service Provider

Add the provider in `app/Providers/AppServiceProvider.php`

```php
public function register()
{
    ...
    $this->app->register('D4nd3v\Test2\CRUDServiceProvider');
}
```

### Step 3: Run Artisan!

Run `php artisan generate:crud TABLE_NAME` from the console.

|Options                           |Description                 |
|:---------------------------------|:---------------------------|
|--overwrite=false                 | overwrite existing files   |
|--model_only=false                | only generates model file  |
|--parent_of=TABLE1,TABLE2,TABLE3  | add links & "belongs to" in model   |
|--child_of=TABLE4,TABLE5,TABLE6   | generates 'parent/pid/resource/rid' |                          |

## Notes
If you need pagination don't forget to run:  
`php artisan vendor:publish --tag=laravel-pagination`

The generated files are:
- app/Http/Controllers/[Resource]CRUDController.php
- app/Models/[Resource].php
- resources/views/[Resource]/create.blade.php
- resources/views/[Resource]/edit.blade.php
- resources/views/[Resource]/index.blade.php
- \+ added route resource in routes/web.php



