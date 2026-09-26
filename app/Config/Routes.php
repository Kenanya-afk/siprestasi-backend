<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->post('auth/register', 'AuthController::register');
$routes->post('auth/login', 'AuthController::login');
$routes->post('auth/logout', 'AuthController::logout', ['filter' => 'auth']);