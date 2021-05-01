<?php

namespace Corbinjurgens\QTrans;

use Illuminate\Support\Facades\Facade as BaseFacade;

use Corbinjurgens\QRoute\ServiceProvider as S;

class Facade extends BaseFacade {
   protected static function getFacadeAccessor() { return S::$name; }
}