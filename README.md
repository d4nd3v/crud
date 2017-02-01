# Laravel 5.3 CRUD Generator

## Usage

### Step 1: Install Through Composer

```
composer require d4nd3v/crud:dev-master
```

### Step 2: Add the Service Provider

Add the provider in `app/Providers/AppServiceProvider.php`

```php
public function register()
{
    ...
	if ($this->app->environment() !== 'production') {
		$this->app->register('D4nd3v\Crud\CrudServiceProvider');
	}
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
- resources/views/[Resource]/view.blade.php
- \+ added route resource in routes/web.php


####For User Roles (or any other filter)####

in ```UsersController.php````
```
	$orderBy = $request->input('by', 'id');
	....
		$roles = array();
		if (class_exists(Role::class)) {
			$roles = Role::get();
		}
		if(!empty(request()->input('role'))) {
			$items = User::role(request()->input('role'))->orderBy($orderBy, $order)->paginate(20);
		} else {
			$items = User::orderBy($orderBy, $order)->paginate(20);
		}
		
        return view('users.index')
            ->withItems($items)
            ->withPage($request->input('page', 1))
            ->withOrder($order)
            ->withOrderBy($orderBy)
            ->withRoles($roles);
```


in ```index.blade.php``` 
```
	@section('content')
	.....
	@if($roles)
		<a href="?role=">all</a>
		@foreach($roles as $role)
			&nbsp; <a href="?role={{ $role->name }}"
					  @if(request()->input('role')==$role->name) class="active" @endif
			>{{ $role->name }}</a>
		@endforeach
		<hr>
	@endif
```








