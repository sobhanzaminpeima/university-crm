<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {
        });

        $this->renderable(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Session expired. Please refresh and try again.',
                ], 419);
            }

            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['session' => 'Session expired (419). Please retry the form.']);
        });

        $this->renderable(function (QueryException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Database error',
                    'details' => $e->getMessage(),
                ], 500);
            }

            if (in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return redirect()->back()
                    ->withInput($request->except(['password', 'password_confirmation']))
                    ->withErrors([
                        'database' => 'Database structure is not fully updated. Please run latest SQL patch/migration.',
                        'technical' => $e->getMessage(),
                    ]);
            }

            return response()->view('errors/friendly', [
                'title' => 'Database Error',
                'message' => 'The system database needs an update before this page can be opened.',
                'details' => $e->getMessage(),
            ], 500);
        });
    }
}
