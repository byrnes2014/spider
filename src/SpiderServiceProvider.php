<?php

namespace Spider;

use Illuminate\Support\ServiceProvider;

class SpiderServiceProvider extends ServiceProvider{
    public function register(){
        $this->app->singleton('Spider',function($arr){
            return new Html($arr);
        });
    }
}