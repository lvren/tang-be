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

// $router->get('/api/sendCode', 'SmsController@sendSmsCode');

// $router->get('/api/orderNotify', 'OrderController@orderNotify');
// $router->get('/api/checkOrder', 'OrderController@checkOrder');
// $router->get('/api/closeOrder', 'PayController@closeOrder');
// $router->get('/api/payOrder', 'PayController@payOrder');
// $router->get('/api/jsConfig', 'PayController@getJsConfig');

// 登录相关
$router->get('/api/login', 'AuthController@userLogin');
$router->get('/api/callback', 'AuthController@getUserAccessToken');
$router->get('/api/isLogin', 'AuthController@isLogin');

// $router->get('/api/getPayParam', 'PayController@getPayParam');
// $router->post('/api/saveInfo', 'PayController@saveOrder');
// $router->get('/api/saveInfo', 'PayController@saveOrder');

// $router->get('/api/reportVisit', 'ReportController@reportView');

$router->get('/api/openid', 'MppAuthController@mAppCode2Session');
$router->post('/api/saveUserInfo', 'MppAuthController@mAppSaveUserInfo');

$router->post('/api/payment', 'MppOrderController@getPayParam');
$router->get('/api/orderCallback', 'MppOrderController@orderCallback');

$router->get('/api/country', 'MppBaseInfoController@getCountryList');
$router->get('/api/school', 'MppBaseInfoController@getSchoolList');
$router->get('/api/sharer', 'MppBaseInfoController@getSharerList');
$router->get('/api/getReferSharer', 'MppBaseInfoController@getReferSharer');
$router->get('/api/getReferBySharer', 'MppBaseInfoController@getReferBySharer');
$router->get('/api/getSharer', 'MppBaseInfoController@getSharerInfo');
$router->get('/api/getOrderInfo', 'MppBaseInfoController@getOrderInfo');

$router->post('/api/add/country', 'AdminController@createCountry');
$router->post('/api/add/school', 'AdminController@createSchool');
$router->post('/api/update/country/{id}', 'AdminController@updateCountry');
$router->post('/api/update/school/{id}', 'AdminController@updateSchool');

$router->get('/api/userProduct', 'MppBaseInfoController@getUserProductList');
$router->get('/api/userInfo', 'MppBaseInfoController@getUserInfo');
$router->get('/api/saveMobile', 'MppBaseInfoController@saveUserMobile');
$router->get('/api/saveWeixin', 'MppBaseInfoController@saveUserWeixin');
$router->get('/api/saveNickname', 'MppBaseInfoController@saveUserNickname');

$router->get('/api/sendCode', 'SmsController@sendSmsCode');

$router->post('/api/upload', 'CosController@upload');
$router->get('/api/download', 'CosController@download');

$router->get('/api/im', 'ImController@sendSmsCode');
$router->get('/api/im/accountImport', 'ImController@accountImport');
$router->get('/api/im/sendToMe', 'ImController@sendToMe');
