<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Sentry\Laravel\Integration;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $user = auth()->user();

            \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($user) {
                if ($user) {
                    $scope->setUser([
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ]);
                }
            });

            Integration::captureUnhandledException($e);
        });
    }
}
