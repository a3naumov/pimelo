<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Controller;

use App\Shared\Attributes\Application\UseCase\Attribute\CreateAttribute\CreateAttributeCommand;
use App\Shared\Attributes\Application\UseCase\Attribute\CreateAttribute\CreateAttributeHandler;
use App\Shared\Attributes\Application\UseCase\Attribute\DeleteAttribute\DeleteAttributeCommand;
use App\Shared\Attributes\Application\UseCase\Attribute\DeleteAttribute\DeleteAttributeHandler;
use App\Shared\Attributes\Application\UseCase\Attribute\DeleteAttributePermanently\DeleteAttributePermanentlyCommand;
use App\Shared\Attributes\Application\UseCase\Attribute\DeleteAttributePermanently\DeleteAttributePermanentlyHandler;
use App\Shared\Attributes\Application\UseCase\Attribute\GetAttribute\GetAttributeHandler;
use App\Shared\Attributes\Application\UseCase\Attribute\GetAttribute\GetAttributeQuery;
use App\Shared\Attributes\Application\UseCase\Attribute\ListAttributes\ListAttributesHandler;
use App\Shared\Attributes\Application\UseCase\Attribute\ListAttributes\ListAttributesQuery;
use App\Shared\Attributes\Application\UseCase\Attribute\RestoreAttribute\RestoreAttributeCommand;
use App\Shared\Attributes\Application\UseCase\Attribute\RestoreAttribute\RestoreAttributeHandler;
use App\Shared\Attributes\Application\UseCase\Attribute\UpdateAttribute\UpdateAttributeCommand;
use App\Shared\Attributes\Application\UseCase\Attribute\UpdateAttribute\UpdateAttributeHandler;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Presenter\AttributePresenter;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Request\Attribute\CreateAttributeRequest;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Request\Attribute\UpdateAttributeRequest;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Resource\Attribute as AttributeResource;
use App\Shared\General\Adapter\Symfony\Http\Error\ErrorResponder;
use App\Shared\General\Adapter\Symfony\Http\OpenApi\ErrorResponse;
use App\Shared\General\Identity\Id;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(path: '/web/attributes', name: 'app.web.attributes.attribute.', format: 'json', stateless: true)]
#[OA\Tag(name: 'Attributes')]
final class AttributeController extends AbstractController
{
    public function __construct(
        private readonly AttributePresenter $presenter,
        private readonly ErrorResponder $errors,
    ) {
    }

    #[Route(path: '/', name: 'list', methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'List attributes', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['attributes'], properties: [new OA\Property(property: 'attributes', type: 'array', items: new OA\Items(ref: new Model(type: AttributeResource::class)))])),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'List attributes (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Only deleted selects deleted attributes; any other value selects active attributes.', schema: new OA\Schema(type: 'string', default: 'active'))]
    public function list(Request $request, ListAttributesHandler $listAttributes): JsonResponse
    {
        try {
            $deleted = 'deleted' === ($request->query->all()['status'] ?? null);

            return $this->json(['attributes' => $this->presenter->many($listAttributes(new ListAttributesQuery($deleted)))]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['GET', 'HEAD'])]
    #[OA\Get(summary: 'Get an attribute', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['attribute'], properties: [new OA\Property(property: 'attribute', ref: new Model(type: AttributeResource::class))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Head(summary: 'Get an attribute (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
        new OA\Response(response: 404, description: 'Resource not found; no response body.'),
        new OA\Response(response: 500, description: 'Unexpected failure; no response body.'),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    #[OA\Parameter(name: 'include_deleted', in: 'query', description: 'Only 1 includes deleted attributes; any other value behaves like 0.', schema: new OA\Schema(type: 'string', default: '0'))]
    public function show(string $id, Request $request, GetAttributeHandler $getAttribute): JsonResponse
    {
        try {
            $includeDeleted = '1' === ($request->query->all()['include_deleted'] ?? null);

            return $this->json([
                'attribute' => $this->presenter->one($getAttribute(new GetAttributeQuery(Id::fromString($id), $includeDeleted))),
            ]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Create an attribute with a name', responses: [
        new OA\Response(response: 201, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['attribute'], properties: [new OA\Property(property: 'attribute', ref: new Model(type: AttributeResource::class))])),
        new ErrorResponse(response: 400),
        new ErrorResponse(response: 415),
        new ErrorResponse(response: 422),
        new ErrorResponse(response: 500),
    ])]
    public function create(#[MapRequestPayload(acceptFormat: 'json')] CreateAttributeRequest $request, CreateAttributeHandler $createAttribute): JsonResponse
    {
        try {
            return $this->json([
                'attribute' => $this->presenter->one($createAttribute(new CreateAttributeCommand($request->name))),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'update', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['PATCH'])]
    #[OA\Patch(summary: 'Update an attribute name, preserving its identity', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['attribute'], properties: [new OA\Property(property: 'attribute', ref: new Model(type: AttributeResource::class))])),
        new ErrorResponse(response: 400),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 415),
        new ErrorResponse(response: 422),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function update(string $id, #[MapRequestPayload(acceptFormat: 'json')] UpdateAttributeRequest $request, UpdateAttributeHandler $updateAttribute): JsonResponse
    {
        try {
            return $this->json([
                'attribute' => $this->presenter->one($updateAttribute(new UpdateAttributeCommand(Id::fromString($id), $request->name))),
            ]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Soft-delete an attribute, preserving its name', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', pattern: '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$'))]
    public function delete(string $id, DeleteAttributeHandler $deleteAttribute): Response
    {
        try {
            $deleteAttribute(new DeleteAttributeCommand(Id::fromString($id)));

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}/restore', name: 'restore', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['POST'])]
    #[OA\Post(summary: 'Restore a deleted attribute with its name', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(type: 'object', required: ['attribute'], properties: [new OA\Property(property: 'attribute', ref: new Model(type: AttributeResource::class))])),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409, description: 'The attribute is already active.'),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function restore(string $id, RestoreAttributeHandler $restoreAttribute): JsonResponse
    {
        try {
            return $this->json(['attribute' => $this->presenter->one($restoreAttribute(new RestoreAttributeCommand(Id::fromString($id))))]);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }

    #[Route(path: '/{id}/permanent', name: 'delete_permanently', requirements: ['id' => '(?i:'.Requirement::UUID.')'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Permanently delete an archived attribute', responses: [
        new OA\Response(response: 204, description: 'Completed; no response body.'),
        new ErrorResponse(response: 404),
        new ErrorResponse(response: 409, description: 'The attribute is still active.'),
        new ErrorResponse(response: 500),
    ])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function deletePermanently(string $id, DeleteAttributePermanentlyHandler $deleteAttributePermanently): Response
    {
        try {
            $deleteAttributePermanently(new DeleteAttributePermanentlyCommand(Id::fromString($id)));

            return new Response(status: Response::HTTP_NO_CONTENT);
        } catch (\Throwable $exception) {
            return $this->errors->respond($exception);
        }
    }
}
