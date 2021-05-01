<?php

use Corbinjurgens\QTrans\ServiceProvider;

function ___($key = null, $replace = [], $locale = null){
	$scope_trans = app(ServiceProvider::$name);
	if (is_null($key)){
		return $scope_trans;
	}
	return $scope_trans->get($key, $replace, $locale);
}

function qtrans($key = null, $replace = [], $locale = null){
	return ___($key, $replace, $locale);
}