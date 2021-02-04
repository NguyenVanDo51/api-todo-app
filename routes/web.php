<?php

/** @var \Laravel\Lumen\Routing\Router $router */

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

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['middleware' => 'auth', 'prefix' => 'api/v1/todo'], function() use($router) {
    $router->post('add', 'TodoController@add');
    $router->post('getlist', 'TodoController@getList');
    $router->post('get', 'TodoController@getOne');
    $router->post('edit', 'TodoController@edit');
    $router->post('delete', 'TodoController@delete');
});

$router->group(['middleware' => 'auth', 'prefix' => 'api/v1/category-todo'], function() use($router) {
    $router->post('create', 'CategoryController@create');
    $router->post('edit', 'CategoryController@edit');
    $router->post('delete', 'CategoryController@delete');
    $router->post('getlist', 'CategoryController@getList');
    $router->post('get', 'CategoryController@getOne');
});
