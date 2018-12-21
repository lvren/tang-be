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
$router->get('/api/closeOrder', 'PayController@closeOrder');
$router->get('/api/payOrder', 'PayController@payOrder');
$router->get('/api/jsConfig', 'PayController@getJsConfig');

$router->get('/api/login', 'AuthController@userLogin');
$router->get('/api/callback', 'AuthController@getUserAccessToken');
$router->get('/api/isLogin', 'AuthController@isLogin');

$router->get('/api/getPayParam', 'PayController@getPayParam');
$router->post('/api/saveInfo', 'PayController@saveOrder');
$router->get('/api/saveInfo', 'PayController@saveOrder');

$router->get('/api/reportVisit', 'ReportController@reportView');

// 临时接口
$router->get('/api/getAdvanceUser', 'AuthController@getAdvanceUser');

$router->get('/mapi/openid', 'MppAuthController@mAppCode2Session');
$router->post('/mapi/saveUserInfo', 'MppAuthController@mAppSaveUserInfo');

$router->post('/mapi/payment', 'MppOrderController@getPayParam');
$router->get('/mapi/orderCallback', 'MppOrderController@orderCallback');

$router->get('/mapi/country', 'MppBaseInfoController@getCountryList');
$router->get('/mapi/sharer', 'MppBaseInfoController@getSharerList');
$router->get('/mapi/userProduct', 'MppBaseInfoController@getUserProductList');
$router->get('/mapi/getSharer', 'MppBaseInfoController@getSharerInfo');
$router->get('/mapi/userInfo', 'MppBaseInfoController@getUserInfo');
$router->get('/mapi/saveMobile', 'MppBaseInfoController@saveUserMobile');
$router->get('/mapi/saveWeixin', 'MppBaseInfoController@saveUserWeixin');
$router->get('/mapi/saveNickname', 'MppBaseInfoController@saveUserNickname');

$router->get('/mapi/sendCode', 'SmsController@sendSmsCode');

$router->get('/mapi/im', 'ImController@sendSmsCode');
$router->get('/mapi/im/accountImport', 'ImController@accountImport');
$router->get('/mapi/im/sendToMe', 'ImController@sendToMe');
