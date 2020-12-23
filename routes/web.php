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

        $router->get('games/winners', 'GameController@winners');
        $router->get('games/prizes', 'GameController@prizesFromConfig');
        $router->get('games/statistics', 'GameController@statistics');

        $router->group(['middleware' => 'auth'], function ($router) {

            $router->get('games/all/summary', 'GameController@summary');

            $router->post('games/create', 'GameController@create');
            $router->post('games/{gameId}/answer', 'GameController@answer');
            $router->post('games/{gameId}/reveal', 'GameController@reveal');
            $router->post('games/{gameId}/collect', 'GameController@collect');
            $router->post('games/{gameId}/pass', 'GameController@pass');
            $router->get('games/{gameId}/prizes', 'GameController@prizes');
            $router->get('games/{gameId}', 'GameController@get');



            $router->get('games/{gameId}/cheat', 'GameController@cheat');

        });


        $router->group(['middleware' => 'admin'], function ($router) {

        });
    });

});