<?php
use App\Http\Middleware\JwtAuth; use Illuminate\Foundation\Application; use Illuminate\Foundation\Configuration\{Exceptions,Middleware};
return Application::configure(basePath:dirname(__DIR__))->withRouting(api:__DIR__.'/../routes/api.php',health:'/up')->withMiddleware(function(Middleware $m){$m->alias(['jwt'=>JwtAuth::class,'admin.access'=>\App\Http\Middleware\AdminAccess::class]);$m->append(\Illuminate\Http\Middleware\HandleCors::class);})->withExceptions(function(Exceptions $e){$e->shouldRenderJsonWhen(fn()=>true);})->create();
