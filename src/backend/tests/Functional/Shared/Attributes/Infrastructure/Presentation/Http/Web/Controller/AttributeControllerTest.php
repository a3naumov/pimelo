<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Attributes\Infrastructure\Presentation\Http\Web\Controller;

use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Entity\Attribute as DoctrineAttribute;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Mapper\AttributeMapper;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Repository\AttributeRepository;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Controller\AttributeController;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Request\Attribute\CreateAttributeRequest;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Request\Attribute\UpdateAttributeRequest;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Resource\Attribute as AttributeResource;
use App\Shared\General\Adapter\Symfony\Identity\UuidGenerator;
use App\Shared\General\Identity\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(AttributeController::class)]
#[UsesClass(CreateAttributeRequest::class)]
#[UsesClass(UpdateAttributeRequest::class)]
#[UsesClass(DoctrineAttribute::class)]
#[UsesClass(AttributeMapper::class)]
#[UsesClass(AttributeRepository::class)]
#[UsesClass(Attribute::class)]
#[UsesClass(AttributeResource::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
final class AttributeControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $this->client->disableReboot();
    }

    // ========================================================================
    // CRUD: responses expose the complete contract and preserve timestamps
    // ========================================================================

    public function testCreatesReadsAndUpdatesAttribute(): void
    {
        $created = $this->createAttribute('  Color  ');
        self::assertSame('Color', $created['name']);
        self::assertInstanceOf(UuidV7::class, Uuid::fromString($created['id']));
        self::assertNull($created['deleted_at']);

        $this->client->request('GET', '/web/attributes/'.strtoupper($created['id']));
        self::assertResponseIsSuccessful();
        self::assertSame(['attribute' => $created], $this->responseData());
        $this->client->request('GET', '/web/attributes/');
        self::assertResponseIsSuccessful();
        self::assertSame(['attributes' => [$created]], $this->responseData());

        $this->client->jsonRequest('PATCH', '/web/attributes/'.$created['id'], ['name' => ' Size ']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $updated = $this->attributeData();
        self::assertSame($created['id'], $updated['id']);
        self::assertSame('Size', $updated['name']);
        self::assertSame($created['created_at'], $updated['created_at']);
        self::assertGreaterThanOrEqual(new \DateTimeImmutable($created['updated_at']), new \DateTimeImmutable($updated['updated_at']));

        $this->client->request('GET', '/web/attributes/'.$created['id']);
        self::assertSame(['attribute' => $updated], $this->responseData());
    }

    public function testAllowsDuplicateNamesIncludingDeletedAttributes(): void
    {
        $first = $this->createAttribute('Color');
        $second = $this->createAttribute('Color');
        self::assertNotSame($first['id'], $second['id']);
        $this->client->request('DELETE', '/web/attributes/'.$first['id']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $third = $this->createAttribute('Color');
        self::assertNotSame($second['id'], $third['id']);
    }

    // ========================================================================
    // Deleted attributes: archive, visibility, restore and permanent deletion
    // ========================================================================

    public function testCompleteDeletionLifecycle(): void
    {
        $created = $this->createAttribute('Color');
        $url = '/web/attributes/'.$created['id'];
        $this->client->request('POST', $url.'/restore');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(['error' => 'Only deleted attributes can be restored.'], $this->responseData());
        $this->client->request('DELETE', $url.'/permanent');
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(['error' => 'Only deleted attributes can be permanently deleted.'], $this->responseData());

        $this->client->request('DELETE', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(['error' => 'Attribute not found.'], $this->responseData());
        $this->client->jsonRequest('PATCH', $url, ['name' => 'Size']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(['error' => 'Attribute not found.'], $this->responseData());
        $this->client->request('GET', '/web/attributes/');
        self::assertSame(['attributes' => []], $this->responseData());

        $this->client->request('GET', $url.'?include_deleted=1');
        self::assertResponseIsSuccessful();
        $deleted = $this->attributeData();
        self::assertNotNull($deleted['deleted_at']);
        self::assertSame($created['created_at'], $deleted['created_at']);
        self::assertSame($created['name'], $deleted['name']);
        $this->client->request('GET', '/web/attributes/?status=deleted');
        self::assertResponseIsSuccessful();
        self::assertSame(['attributes' => [$deleted]], $this->responseData());

        $this->client->request('POST', $url.'/restore');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $restored = $this->attributeData();
        self::assertNull($restored['deleted_at']);
        self::assertSame($created['created_at'], $restored['created_at']);
        self::assertSame($created['id'], $restored['id']);
        self::assertSame($created['name'], $restored['name']);
        $this->client->request('GET', '/web/attributes/?status=deleted');
        self::assertSame(['attributes' => []], $this->responseData());

        $this->client->request('DELETE', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->client->request('DELETE', $url.'/permanent');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());
        $this->client->request('GET', $url.'?include_deleted=1');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(['error' => 'Attribute not found.'], $this->responseData());
    }

    // ========================================================================
    // Validation: requests reject malformed names before writing any data
    // ========================================================================

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidCreateAndUpdate(array $payload): void
    {
        $created = $this->createAttribute('Original');

        foreach ([['POST', '/web/attributes/'], ['PATCH', '/web/attributes/'.$created['id']]] as [$method, $url]) {
            $this->client->jsonRequest($method, $url, $payload);
            self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
            self::assertResponseHeaderSame('Content-Type', 'application/json');
            $error = $this->responseData();
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $error['status']);
            self::assertSame('name', $error['violations'][0]['propertyPath']);
            self::assertIsString($error['violations'][0]['title']);
        }

        $this->client->request('GET', '/web/attributes/');
        self::assertSame(['attributes' => [$created]], $this->responseData());
    }

    public function testAcceptsUnicodeNameAtTheLengthLimit(): void
    {
        $name = str_repeat('😀', 255);
        self::assertSame($name, $this->createAttribute(' '.$name.' ')['name']);
    }

    public function testRejectsMalformedJsonAndUnsupportedContentType(): void
    {
        $this->client->request('POST', '/web/attributes/', server: ['CONTENT_TYPE' => 'application/json'], content: '{"name":');
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame(Response::HTTP_BAD_REQUEST, $this->responseData()['status']);
        $this->client->request('POST', '/web/attributes/', server: ['CONTENT_TYPE' => 'text/plain'], content: 'Color');
        self::assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $this->responseData()['status']);
    }

    // ========================================================================
    // HTTP semantics: missing identities, HEAD, methods and fallback visibility
    // ========================================================================

    public function testMissingAttributesReturnNotFound(): void
    {
        $url = '/web/attributes/01994731-abcd-7000-8000-000000000001';

        foreach ([['GET', $url], ['PATCH', $url], ['DELETE', $url], ['POST', $url.'/restore'], ['DELETE', $url.'/permanent']] as [$method, $target]) {
            $this->client->jsonRequest($method, $target, ['name' => 'Color']);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
            self::assertSame(['error' => 'Attribute not found.'], $this->responseData());
        }

        $this->client->request('GET', '/web/attributes/invalid');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testHeadAndUnknownVisibilityValues(): void
    {
        $created = $this->createAttribute('Color');

        foreach (['unknown', '1', ''] as $status) {
            $this->client->request('GET', '/web/attributes/?status='.$status);
            self::assertResponseIsSuccessful();
            self::assertSame(['attributes' => [$created]], $this->responseData());
        }

        foreach (['/web/attributes/', '/web/attributes/'.$created['id']] as $url) {
            $this->client->request('HEAD', $url);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', 'application/json');
            self::assertSame('', $this->client->getResponse()->getContent());
        }

        $this->client->request('PUT', '/web/attributes/');
        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseHeaderSame('Allow', 'GET, HEAD, POST');
    }

    public function testUnexpectedFailuresUseTheSharedErrorResponse(): void
    {
        $repository = $this->createStub(AttributeRepositoryInterface::class);
        $repository->method('findAll')->willThrowException(new \RuntimeException('Private database failure.'));
        self::getContainer()->set(AttributeRepositoryInterface::class, $repository);
        $this->client->request('GET', '/web/attributes/');
        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertSame(['error' => 'Internal server error.'], $this->responseData());
    }

    private function createAttribute(string $name): array
    {
        $this->client->jsonRequest('POST', '/web/attributes/', ['name' => $name]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->attributeData();
    }

    private function attributeData(): array
    {
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = $this->responseData();
        self::assertSame(['attribute'], array_keys($data));
        $attribute = $data['attribute'];
        self::assertSame(['id', 'name', 'created_at', 'updated_at', 'deleted_at'], array_keys($attribute));
        self::assertTrue(Uuid::isValid($attribute['id']));
        self::assertIsString($attribute['name']);

        foreach (['created_at', 'updated_at', 'deleted_at'] as $field) {
            if ('deleted_at' === $field && null === $attribute[$field]) {
                continue;
            }

            self::assertIsString($attribute[$field]);
            self::assertSame($attribute[$field], new \DateTimeImmutable($attribute[$field])->format(\DateTimeInterface::ATOM));
        }

        return $attribute;
    }

    private function responseData(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function invalidPayloads(): iterable
    {
        yield 'missing name' => [[]];

        yield 'null name' => [['name' => null]];

        yield 'blank name' => [['name' => " \t "]];

        yield 'empty name' => [['name' => '']];

        yield 'integer name' => [['name' => 42]];

        yield 'array name' => [['name' => []]];

        yield 'too long' => [['name' => str_repeat('a', 256)]];

        yield 'too many unicode characters' => [['name' => str_repeat('😀', 256)]];
    }
}
