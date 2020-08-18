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

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/who', function () use ($router) {
    return "Game Center Service";
});


$router->group(['prefix' => 'api/v1'], function ($router) {

    $router->group(['namespace' => 'Api\V1'], function ($router) {


        $router->group(['middleware' => 'auth'], function ($router) {

            $router->get('games/all/summary', 'GameController@summary');

            $router->post('games/create', 'GameController@create');
            $router->post('games/{gameId}/answer', 'GameController@answer');
            $router->post('games/{gameId}/reveal', 'GameController@reveal');
            $router->post('games/{gameId}/collect', 'GameController@collect');
            $router->post('games/{gameId}/pass', 'GameController@pass');
            $router->get('games/{gameId}', 'GameController@get');

        });


        $router->group(['middleware' => 'admin'], function ($router) {

        });
    });

});