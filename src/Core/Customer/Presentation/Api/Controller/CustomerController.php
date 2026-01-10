<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Presentation\Api\Controller;

use Pimelo\Core\Customer\Application\Exception\Customer\CustomerAlreadyExistsException;
use Pimelo\Core\Customer\Application\Service\Customer\CustomerService;
use Pimelo\Core\Customer\Application\UseCase\Command\Customer\RegisterCustomer\RegisterCustomerCommand;
use Pimelo\Core\Customer\Presentation\Api\Request\Customer\RegisterCustomerRequest;
use Pimelo\Core\Customer\Presentation\Api\Resource\Customer\CustomerResource;
use Pimelo\Shared\Auth\AuthenticationUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/api/v1/customers', name: 'app.api.v1.customers.customer.', format: 'json', stateless: true)]
class CustomerController
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {
    }

    #[Route(path: '/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] AuthenticationUserInterface $user): JsonResponse
    {
        return new JsonResponse(data: [
            'customer' => new CustomerResource($this->customerService->getAuthenticationUserDetails($user)),
        ]);
    }

    #[Route(path: '/register', name: 'register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterCustomerRequest $request,
    ): JsonResponse {
        try {
            $id = $this->customerService->register(new RegisterCustomerCommand(
                email: $request->getEmail(),
                plainPassword: $request->getPassword(),
            ));
        } catch (CustomerAlreadyExistsException $e) {
            return new JsonResponse(data: ['error' => $e->getMessage()], status: JsonResponse::HTTP_CONFLICT);
        }

        return new JsonResponse(data: ['id' => $id], status: JsonResponse::HTTP_CREATED);
    }
}
