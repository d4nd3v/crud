<?php

namespace D4nd3v\Crud;

use Illuminate\Support\ServiceProvider;

class CRUDServiceProvider extends ServiceProvider {

    protected $commands = [
        'D4nd3v\Crud\CRUDCommand',
    ];

    public function register(){
        $this->commands($this->commands);
    }
}