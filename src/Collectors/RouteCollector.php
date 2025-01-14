<?php

declare(strict_types=1);

namespace Napp\Xray\Collectors;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Pkerrigan\Xray\Segment;

class RouteCollector extends EventsCollector
{
    private string $segmentId;

    public function registerEventListeners(): void
    {
        $this->app->booted(function () {
            $segment = (new Segment())->setName('route matching');
            $this->segmentId = $this->addSegment($segment)->getId();
        });

        // Time between route resolution and request handled
        $this->app['events']->listen(RouteMatched::class, function ($event) {
            $this->endSegment($this->segmentId);

            try {
                $segment = (new Segment())
                    ->setName('request handled')
                    ->addAnnotation('controller', $this->getController())
                    ->end();
                $this->segmentId = $this->addSegment($segment)->getId();
            } catch (\Exception $e) {
                $this->handleException($e);
            }
        });

        $this->app['events']->listen(RequestHandled::class, function () {
            $this->endSegment($this->segmentId);
        });
    }

    protected function getController(): string
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];

        $route = $router->current();
        $controller = $route ? $route->getActionName() : 'unknown route';

        if ($controller instanceof \Closure) {
            return 'anonymous function';
        }
        if (is_object($controller)) {
            return 'instance of ' . get_class($controller);
        }
        if (is_array($controller) && 2 === count($controller)) {
            if (is_object($controller[0])) {
                $controller = get_class($controller[0]) . '->' . $controller[1];
            } else {
                $controller = $controller[0] . '::' . $controller[1];
            }
        } elseif (!is_string($controller)) {
            return 'unknown route';
        }

        return $controller;
    }
}
