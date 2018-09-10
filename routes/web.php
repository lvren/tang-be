<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
 */

$router->get('/api/sendCode', 'SmsController@sendSmsCode');

$router->get('/api/orderNotify', 'OrderController@orderNotify');
$router->get('/api/checkOrder', 'OrderController@checkOrder');

$router->get('/api/login', 'AuthController@userLogin');
$router->get('/api/callback', 'AuthController@getUserAccessToken');
$router->get('/api/isLogin', 'AuthController@isLogin');

$router->get('/api/getPayParam', 'PayController@getPayParam');
$router->post('/api/saveInfo', 'PayController@saveOrder');
$router->get('/api/saveInfo', 'PayController@saveOrder');
