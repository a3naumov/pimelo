<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ServiceProxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProxyController
{
    #[Route('/{path}', name: 'app.proxy', requirements: ['path' => '.+'], priority: -100, stateless: true)]
    public function __invoke(Request $request, ServiceProxy $proxy): Response
    {
        return $proxy->forward($request);
    }
}
