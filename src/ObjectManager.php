<?php

namespace BenTools\MeilisearchOdm;

use AutoMapper\AutoMapper;
use AutoMapper\AutoMapperInterface;
use BenTools\MeilisearchOdm\Attribute\AsMeiliDocument as ClassMetadata;
use BenTools\MeilisearchOdm\Contract\ObjectManager as ObjectManagerInterface;
use BenTools\MeilisearchOdm\Contract\ObjectRepository as ObjectRepositoryInterface;
use InvalidArgumentException;
use Meilisearch\Client;
use ReflectionClass;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

use function array_column;
use function BenTools\IterableFunctions\iterable;
use function BenTools\IterableFunctions\iterable_chunk;
use function sprintf;

use const PHP_INT_MAX;

final class ObjectManager implements ObjectManagerInterface
{
    private const DEFAULT_OPTIONS = [
        'flushBatchSize' => PHP_INT_MAX,
        'flushTimeoutMs' => 5000,
        'flushCheckIntervalMs' => 50,
    ];

    private UnitOfWork $uow;

    /**
     * @var array<class-string, ClassMetadata>
     */
    private array $classMetadata = [];

    /**
     * @var array{flushBatchSize: int, flushTimeoutMs: int, flushCheckIntervalMs: int}
     */
    private readonly array $options;

    public readonly AutoMapperInterface $mapper;

    /**
     * @var array<class-string, ObjectRepositoryInterface>
     */
    private array $repositories = [];

    /**
     * @param array{flushBatchSize?: int, flushTimeoutMs?: int, flushCheckIntervalMs?: int} $options
     */
    public function __construct(
        public readonly Client $meili = new Client('http://localhost:7700'),
        private readonly SerializerInterface $serializer = new Serializer(),
        private readonly PropertyAccessorInterface $propertyAccessor = new PropertyAccessor(),
        ?AutoMapperInterface $mapper = null,
        array $options = self::DEFAULT_OPTIONS,
    ) {
        $this->uow = new UnitOfWork();
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults(self::DEFAULT_OPTIONS);
        $optionsResolver->setAllowedTypes('flushBatchSize', ['int']);
        $optionsResolver->setAllowedTypes('flushTimeoutMs', ['int']);
        $optionsResolver->setAllowedTypes('flushCheckIntervalMs', ['int']);
        $this->options = $optionsResolver->resolve($options);
        $this->mapper = $mapper ?? AutoMapper::create();
    }

    public function getRepository(string $className): ObjectRepositoryInterface
    {
        return $this->repositories[$className] ??= new ObjectRepository($className, $this, $this->mapper);
    }

    public function clear(): void
    {
        foreach ($this->repositories as $repository) {
            $repository->clear();
        }
    }

    public function scheduleAddOrUpdate(object $object, object ...$objects): void
    {
        $objects = [$object, ...$objects];
        foreach ($objects as $object) {
            $this->uow->scheduleAddOrUpdate($object, $this->readClassMetadata($object::class));
        }
    }

    public function scheduleAddOrReplace(object $object, object ...$objects): void
    {
        $objects = [$object, ...$objects];
        foreach ($objects as $object) {
            $this->uow->scheduleAddOrReplace($object, $this->readClassMetadata($object::class));
        }
    }

    public function persist(object $object, object ...$objects): void
    {
        $this->scheduleAddOrUpdate($object, ...$objects);
    }

    public function remove(object $object, object ...$objects): void
    {
        $objects = [$object, ...$objects];
        foreach ($objects as $object) {
            $this->uow->scheduleDelete($object, $this->readClassMetadata($object::class));
        }
    }

    public function contains(object $object): bool
    {
        return $this->uow->contains($object);
    }

    public function flush(): void
    {
        $tasks = [];
        foreach ($this->uow->getIndexUids() as $indexUid) {
            $scheduledForAddOrUpdate = self::getDocumentsByBatches(
                $this->uow->getScheduledForAddOrUpdate($indexUid),
                $this->options['flushBatchSize'],
            );

            foreach ($scheduledForAddOrUpdate as $objects) {
                $tasks[] = $this->meili->index($indexUid)->updateDocuments(
                    iterable($objects)
                        ->map(
                            fn (object $object) => $this->serializer->serialize(
                                $object,
                                'json',
                                $this->readClassMetadata($object::class)->serializationContext,
                            ),
                        )
                        ->asArray(),
                );
            }

            $scheduledForAddOrReplace = self::getDocumentsByBatches(
                $this->uow->getScheduledForAddOrReplace($indexUid),
                $this->options['flushBatchSize'],
            );

            foreach ($scheduledForAddOrReplace as $objects) {
                $tasks[] = $this->meili->index($indexUid)->updateDocuments(
                    iterable($objects)
                        ->map(
                            fn (object $object) => $this->serializer->serialize(
                                $object,
                                'json',
                                $this->readClassMetadata($object::class)->serializationContext,
                            ),
                        )
                        ->asArray(),
                );
            }

            $scheduledForDelete = self::getDocumentsByBatches(
                $this->uow->getScheduledForDelete($indexUid),
                $this->options['flushBatchSize'],
            );

            foreach ($scheduledForDelete as $objects) {
                foreach ($objects as $object) {
                    $tasks[] = $this->meili->index($indexUid)->deleteDocument($this->readPrimaryKey($object));
                }
            }
        }

        $this->meili->waitForTasks(
            array_column($tasks, 'taskUid'),
            $this->options['flushTimeoutMs'],
            $this->options['flushCheckIntervalMs'],
        );

        unset($this->uow);
        $this->uow = new UnitOfWork();
    }

    public function readClassMetadata(string $className): ClassMetadata
    {
        return $this->classMetadata[$className]
            ??= (((new ReflectionClass($className))
            ->getAttributes(ClassMetadata::class)[0]
            ?? throw new InvalidArgumentException(
                sprintf('Class %s is not configured as a Meilisearch document.', $className),
            )))
            ->newInstance();
    }


    /**
     * @return iterable<object>[]
     */
    private static function getDocumentsByBatches(iterable $documents, int $batchSize): array
    {
        if (PHP_INT_MAX === $batchSize) {
            return [$documents];
        }

        return iterable_chunk($documents, $batchSize);
    }

    public function readPrimaryKey(object $object): string
    {
        return $this->propertyAccessor->getValue($object, $this->readClassMetadata($object::class)->primaryKey);
    }
}
