<?php declare(strict_types=1);

namespace Foo;

require_once(__DIR__ . '/../vendor/autoload.php');

use function bin2hex;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefill;
use function imagepng;
use function ob_end_clean;
use function ob_start;
use function random_bytes;
use function random_int;
use function sys_get_temp_dir;
use function tempnam;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;

ob_start();

$dispatcher = new EventDispatcher();
$control_resolver = new ControllerResolver();

$kernel = new HttpKernel($dispatcher, $control_resolver);

$store = new Store(__DIR__ . '/../cache/');

$cache = new HttpCache($kernel, $store);

$dispatcher->addListener(
    KernelEvents::REQUEST,
    static function (RequestEvent $event) : void {
        if ($event->getRequest()->query->has('text')) {
            $response = new Response(bin2hex(random_bytes(16)));
        } else {
            $gd = imagecreatetruecolor(64, 64);

            $colour = imagecolorallocate(
                $gd,
                random_int(0, 255),
                random_int(0, 255),
                random_int(0, 255)
            );

            imagefill($gd, 0, 0, $colour);

            $file = tempnam(sys_get_temp_dir(), 'symfony/symfony#9128');

            imagepng($gd, $file);

            $response = new BinaryFileResponse($file);

            $response->headers->set('Content-Type', 'image/png');
        }

        $response->setPublic(true);

        $response->setTtl(5);

        $event->setResponse($response);
    }
);

$request = Request::createFromGlobals();
$a = $cache->handle($request);
$a->prepare($request);

ob_end_clean();

if (isset($_GET['debug'])) {
    usleep(1000);

    $request = Request::createFromGlobals();
    $b = $cache->handle($request);
    $b->prepare($request);

    var_dump(
        $a->getStatusCode(),
        $a->headers->get('Content-Length'),
        $b->getStatusCode(),
        $b->headers->get('Content-Length')
    );exit(1);
} else {
    $a->send();
}

$cache->terminate();
