<?php namespace Modules\System\Http;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;

class Kernel extends HttpKernel
{
    protected $routeMiddleware = [
        'vividcaseapi'=>\Modules\Vividcase\Http\Middleware\Authenticate::class
    ];

    protected $middlewareGroups = [
        'vividcaseapi'   => [
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
//            \Modules\Vividcase\Http\Middleware\Authenticate::class
        ],
    ];

    public function __construct(Application $app, Router $router)
    {
        parent::__construct($app, $router);
    }
}
