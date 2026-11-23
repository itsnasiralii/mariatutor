<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ParentController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TutorController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/explore-tutors', [HomeController::class, 'exploreTutors'])->name('explore.tutors');
Route::get('/contract', [HomeController::class, 'contract'])->name('contract');
Route::get('/privacy', [HomeController::class, 'privacy'])->name('privacy');
Route::get('/healthz', [HomeController::class, 'healthz'])->name('healthz');

// Authentication Routes
Route::get('/login/{portal?}', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/parent/login', [AuthController::class, 'showParentLogin'])->name('auth.parent_login');
Route::get('/tutor/login', [AuthController::class, 'showTutorLogin'])->name('auth.tutor_login');
Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('auth.admin_login');

Route::get('/register/parent', [AuthController::class, 'showRegisterParent'])->name('auth.register_parent');
Route::post('/register/parent', [AuthController::class, 'registerParent'])->name('auth.register_parent.post');
Route::get('/register/tutor', [AuthController::class, 'showRegisterTutor'])->name('auth.register_tutor');
Route::post('/register/tutor', [AuthController::class, 'registerTutor'])->name('auth.register_tutor.post');

// Parent Protected Routes
Route::middleware(['auth', 'role:parent'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('/dashboard', [ParentController::class, 'dashboard'])->name('dashboard');
    Route::get('/requests/new', [ParentController::class, 'showRequestForm'])->name('request_new');
    Route::post('/requests/new', [ParentController::class, 'submitRequest'])->name('request_submit');
});

// Tutor Protected Routes
Route::middleware(['auth', 'role:tutor'])->prefix('tutor')->name('tutor.')->group(function () {
    Route::get('/dashboard', [TutorController::class, 'dashboard'])->name('dashboard');
    Route::post('/opportunities/{id}/interest', [TutorController::class, 'expressInterest'])->name('express_interest');
    Route::get('/profile', [TutorController::class, 'profile'])->name('profile');
    Route::post('/profile', [TutorController::class, 'updateProfile'])->name('profile_update');
    Route::post('/documents/upload', [TutorController::class, 'uploadDocument'])->name('document_upload');
});

// Admin Protected Routes
Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/tutors', [AdminController::class, 'tutors'])->name('tutors');
    Route::get('/tutors/{id}', [AdminController::class, 'tutorDetail'])->name('tutor_detail');
    Route::post('/tutors/{id}/verify', [AdminController::class, 'verifyTutor'])->name('tutor_verify');
    Route::get('/requests', [AdminController::class, 'requests'])->name('requests');
    Route::get('/requests/{id}', [AdminController::class, 'requestDetail'])->name('request_detail');
    Route::post('/requests/{id}/assign', [AdminController::class, 'assignTutor'])->name('request_assign');
});

// SuperAdmin Protected Routes
Route::middleware(['auth', 'role:super_admin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/catalog', [SuperAdminController::class, 'catalog'])->name('catalog');
    Route::post('/catalog', [SuperAdminController::class, 'storeCatalogItem'])->name('catalog_store');
    Route::get('/commission', [SuperAdminController::class, 'commissionSettings'])->name('commission');
    Route::post('/commission', [SuperAdminController::class, 'updateCommission'])->name('commission_update');
    Route::post('/settings', [SuperAdminController::class, 'updateSettings'])->name('settings_update');
});
