<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


/*
|--------------------------------------------------------------------------
| Import Routes
|--------------------------------------------------------------------------
| Description
|
*/
Route::get('import/upload','ImportController@showFormUpload')->name('get.upload.file');
Route::get('import/reader','ImportController@readSettings')->name('get.reader');
