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
$routes->put('pengajuan/(:num)/lecturer-reviewers', 'PengajuanController::setLecturerReviewers/$1', ['filter' => 'auth:admin,superadmin']);
$routes->post('pv-questionnaire', 'PvQuestionnaireController::create', ['filter' => 'auth:admin,superadmin']);
$routes->post('pv-questionnaire/(:num)/questions', 'PvQuestionnaireController::addQuestion/$1', ['filter' => 'auth:admin,superadmin']);
$routes->get('pv-questionnaire/active', 'PvQuestionnaireController::active', ['filter' => 'auth']);
$routes->get('pv-respond/(:any)', 'PvResponseController::form/$1');
$routes->post('pv-respond/(:any)', 'PvResponseController::submit/$1');
$routes->put('pengajuan/(:num)/validasi/(:segment)', 'ValidasiController::updateStatus/$1/$2', ['filter' => 'auth:admin,superadmin']);
$routes->put('pengajuan/(:num)/validasi/(:segment)', 'ValidasiController::updateStatus/$1/$2', ['filter' => 'auth:admin,superadmin']);

// Superadmin
$routes->get('superadmin/users', 'SuperAdminController::users', ['filter' => 'auth:superadmin']);
$routes->put('superadmin/users/(:num)/role', 'SuperAdminController::updateRole/$1', ['filter' => 'auth:superadmin']);
$routes->delete('superadmin/users/(:num)', 'SuperAdminController::delete/$1', ['filter' => 'auth:superadmin']);
$routes->get('superadmin/logs', 'LogController::all', ['filter' => 'auth:superadmin']);

// Admin: hanya log aksi milik sendiri
$routes->get('admin/logs', 'LogController::mine', ['filter' => 'auth:admin']);

// Profile (semua role yang sudah login)
$routes->get('profile', 'ProfileController::show', ['filter' => 'auth']);
$routes->put('profile', 'ProfileController::update', ['filter' => 'auth']);
$routes->put('profile/password', 'ProfileController::changePassword', ['filter' => 'auth']);
$routes->get('profile/picture', 'ProfileController::picture', ['filter' => 'auth']);
$routes->post('profile/picture', 'ProfileController::uploadPicture', ['filter' => 'auth']);
$routes->delete('profile/picture', 'ProfileController::deletePicture', ['filter' => 'auth']);

// Registration OTP
$routes->post('auth/register/otp', 'RegistrationOtpController::sendOtp');
$routes->post('auth/register/otp/verify', 'RegistrationOtpController::verifyOtp');

//Lupa Password
$routes->post('auth/forgot-password', 'ForgotPasswordController::sendOtp');
$routes->post('auth/forgot-password/verify', 'ForgotPasswordController::verifyOtp');
$routes->post('auth/forgot-password/reset', 'ForgotPasswordController::reset');