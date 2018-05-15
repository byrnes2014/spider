<?php

namespace Spider;

use Illuminate\Support\Facades\Facade;

class SpiderFacade extends Facade{

    protected static function getFacadeAccessor() {
        return 'Spider';
    }

}