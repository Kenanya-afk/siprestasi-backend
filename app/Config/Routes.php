<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->post('auth/register', 'AuthController::register');
$routes->post('auth/login', 'AuthController::login');
$routes->post('auth/logout', 'AuthController::logout', ['filter' => 'auth']);
$routes->post('pengajuan', 'PengajuanController::create', ['filter' => 'auth:mahasiswa']);
$routes->get('pengajuan/me', 'PengajuanController::me', ['filter' => 'auth:mahasiswa']);
$routes->post('pengajuan/prestasi', 'PengajuanController::uploadPrestasi', ['filter' => 'auth:mahasiswa']);
$routes->post('pengajuan/organisasi', 'PengajuanController::uploadOrganisasi', ['filter' => 'auth:mahasiswa']);
$routes->post('pengajuan/bahasa', 'PengajuanController::uploadBahasa', ['filter' => 'auth:mahasiswa']);
$routes->put('pengajuan/peer-reviewers', 'PengajuanController::setPeerReviewers', ['filter' => 'auth:mahasiswa']);
$routes->post('pengajuan/submit', 'PengajuanController::submit', ['filter' => 'auth:mahasiswa']);
$routes->get('pengajuan/ranking', 'PengajuanController::ranking', ['filter' => 'auth:admin,superadmin']);
$routes->post('periode', 'PeriodeController::create', ['filter' => 'auth:admin,superadmin']);
$routes->get('periode/current', 'PeriodeController::current', ['filter' => 'auth']);