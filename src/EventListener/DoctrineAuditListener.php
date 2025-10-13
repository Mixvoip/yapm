<?php

/**
 * @author bsteffan
 * @since 2025-07-14
 */

namespace App\EventListener;

use App\Entity\Enums\AuditAction;
use App\Service\Audit\AuditableEntityInterface;
use App\Service\Audit\AuditService;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use ReflectionClass;
use ReflectionProperty;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postUpdate)]
class DoctrineAuditListener
{
    private array $preUpdateData = [];

    /**
     * @param  AuditService  $auditService
     */
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Log entity creation.
     *
     * @param  PostPersistEventArgs  $args
     *
     * @return void
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof AuditableEntityInterface)) {
            return;
        }

        $newValues = $this->extractEntityData($entity);
        $this->auditService->log(AuditAction::Created, $entity, newValues: $newValues);
    }

    /**
     * Handle entity updates - capture old values.
     *
     * @param  PreUpdateEventArgs  $args
     *
     * @return void
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof AuditableEntityInterface)) {
            return;
        }

        $entityId = $entity->getId();
        $changeSet = $args->getEntityChangeSet();

        // Store old values for use in postUpdate
        $this->preUpdateData[$entityId] = [];
        foreach ($changeSet as $fieldName => $change) {
            $oldValue = $change[0];
            $this->preUpdateData[$entityId][$fieldName] = $this->formatValue($oldValue);
        }
    }

    /**
     * Handle entity updates - log with old and new values.
     *
     * @param  PostUpdateEventArgs  $args
     *
     * @return void
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof AuditableEntityInterface)) {
            return;
        }
        $entityId = $entity->getId();
        $oldValues = $this->preUpdateData[$entityId] ?? [];

        // Only log if we have old values (should always be the case)
        if (empty($oldValues)) {
            return;
        }

        $newValues = $this->extractEntityData($entity);

        $this->auditService->log(
            AuditAction::Updated,
            $entity,
            $oldValues,
            array_intersect_key($newValues, $oldValues) // Only new values for changed fields
        );

        // Clean up stored data
        unset($this->preUpdateData[$entityId]);
    }

    /**
     * Handle entity deletion.
     *
     * @param  PostRemoveEventArgs  $args
     *
     * @return void
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof AuditableEntityInterface)) {
            return;
        }

        $oldValues = $this->extractEntityData($entity);

        $this->auditService->log(
            AuditAction::Deleted,
            $entity,
            $oldValues
        );
    }

    /**
     * Extract data from entity for logging.
     *
     * @param  object  $entity
     *
     * @return array
     */
    private function extractEntityData(object $entity): array
    {
        $data = [];
        $reflection = new ReflectionClass($entity);
        $properties = $this->getAllPropertiesIncludingInherited($reflection);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            try {
                if ($property->hasType() && !$property->isInitialized($entity)) {
                    continue;
                }

                $value = $property->getValue($entity);

                $formattedValue = $this->formatValue($value);
                $data[$propertyName] = $formattedValue;
            } catch (Exception) {
                // Skip properties that can't be accessed
            }
        }

        return $data;
    }

    /**
     * Get all properties from a class including inherited properties from parent classes.
     *
     * @param  ReflectionClass  $reflection
     *
     * @return ReflectionProperty[]
     */
    private function getAllPropertiesIncludingInherited(ReflectionClass $reflection): array
    {
        $properties = [];

        // Start with current class and walk up the inheritance chain
        $currentClass = $reflection;

        while ($currentClass !== false) {
            $classProperties = $currentClass->getProperties(
                ReflectionProperty::IS_PUBLIC |
                ReflectionProperty::IS_PROTECTED |
                ReflectionProperty::IS_PRIVATE
            );

            foreach ($classProperties as $property) {
                // Avoid duplicates (in case of property overriding)
                $propertyName = $property->getName();
                if (!isset($properties[$propertyName])) {
                    $properties[$propertyName] = $property;
                }
            }

            // Move to parent class
            $currentClass = $currentClass->getParentClass();
        }

        return array_values($properties);
    }

    /**
     * Format a value consistently for audit logging.
     *
     * @param  mixed  $value
     *
     * @return mixed
     */
    private function formatValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof AuditableEntityInterface) {
            return $value->__toString();
        }

        return $value;
    }
}
