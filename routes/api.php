<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:api')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::get('Users', 'UserController@index')->middleware('azure');
Route::post('Users', 'UserController@store')->middleware('azure');
Route::delete('Users/{id}', 'UserController@delete')->middleware('azure');

Route::get('Groups', 'GroupController@index')->middleware('azure');
Route::post('Groups', 'GroupController@store')->middleware('azure');
