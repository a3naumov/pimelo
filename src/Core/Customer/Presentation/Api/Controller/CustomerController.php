<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Presentation\Api\Controller;

use Pimelo\Core\Customer\Application\Exception\Customer\CustomerAlreadyExistsException;
use Pimelo\Core\Customer\Application\Service\CustomerService;
use Pimelo\Core\Customer\Presentation\Api\Request\Customer\RegisterCustomerRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/customers', name: 'app.api.v1.customer.', format: 'json', stateless: true)]
class CustomerController
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {
    }

    #[Route(path: '/register', name: 'register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterCustomerRequest $request,
    ): JsonResponse {
        try {
            $this->customerService->register($request);
        } catch (CustomerAlreadyExistsException $e) {
            return new JsonResponse(data: ['error' => $e->getMessage()], status: JsonResponse::HTTP_CONFLICT);
        }

        return new JsonResponse(data: null, status: JsonResponse::HTTP_CREATED);
    }
}
