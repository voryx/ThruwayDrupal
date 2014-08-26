<?php

/**
 * @file
 * Definition of Drupal\thruway\Plugin\thruway\resource\EntityResource.
 */

namespace Drupal\thruway\Plugin\thruway\resource;

use Drupal\Core\Entity\Query\Sql\Query;
use Drupal\thruway\Annotation\Thruway;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\thruway\Plugin\ResourceBase;
use Drupal\thruway\Annotation\ThruwayResource;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;

/**
 * Represents entities as resources.
 *
 * @ThruwayResource(
 *   id = "entity",
 *   label = @Translation("Entity"),
 *   serialization_class = "Drupal\Core\Entity\Entity",
 *   deriver = "Drupal\rest\Plugin\Derivative\EntityDerivative"
 * )
 */
class EntityResource extends ResourceBase
{


    /**
     * Gets all entities.
     *
     * @Thruway(name = "getAll", type="procedure")
     * @param $args
     * @return array
     * @internal param null $bundle
     */
    public function getAll($args)
    {

        $ids = \Drupal::entityQuery("node")
            ->condition('status', 1)
            ->condition('type', $args['type'])
            ->execute();

        $entities = entity_load_multiple("node", $ids);

        $return = [];

        foreach ($entities as $entity) {

            //@todo add permissions check
            if (!$entity->access('view')) {
                throw new AccessDeniedHttpException();
            }
            foreach ($entity as $field_name => $field) {
                if (!$field->access('view')) {
                    unset($entity->{$field_name});
                }
            }

            /**
             * Test stuff
             */
            $serializer = \Drupal::service('serializer');
            $return[] = $serializer->serialize($entity, 'array');

//            $return[] = $entity->toArray();
        }
        return [$return];
    }


    /**
     * Responds to entity GET requests.
     *
     * @Thruway(name = "get", type="procedure")
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity object.
     *
     * @return \mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function get(EntityInterface $entity)
    {
        if (!$entity->access('view')) {
            throw new AccessDeniedHttpException();
        }
        foreach ($entity as $field_name => $field) {
            if (!$field->access('view')) {
                unset($entity->{$field_name});
            }
        }
        return $entity;
    }

    /**
     * Responds to entity POST requests and saves the new entity.
     *
     * @Thruway(name = "add", type="procedure")
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *
     * @return array
     *
     */
    public function post(EntityInterface $entity = null)
    {
        if ($entity == null) {
            throw new BadRequestHttpException(t('No entity content received.'));
        }

        if (!$entity->access('create')) {
            throw new AccessDeniedHttpException();
        }
        $definition = $this->getPluginDefinition();
        // Verify that the deserialized entity is of the type that we expect to
        // prevent security issues.
        if ($entity->getEntityTypeId() != $definition['entity_type']) {
            throw new BadRequestHttpException(t('Invalid entity type'));
        }
        // POSTed entities must not have an ID set, because we always want to create
        // new entities here.
        if (!$entity->isNew()) {
            throw new BadRequestHttpException(t('Only new entities can be created'));
        }
        foreach ($entity as $field_name => $field) {
            if (!$field->access('create')) {
                throw new AccessDeniedHttpException(
                    t('Access denied on creating field @field.', array('@field' => $field_name))
                );
            }
        }

        // Validate the received data before saving.
        $this->validate($entity);
        try {
            $entity->save();

            //@todo fix this hack.  When you save, it doesn't load the field defaults, so we need to reload it
            return $entity::load($entity->id());

//            $this->logger->notice(
//                'Created entity %type with ID %id.',
//                array('%type' => $entity->getEntityTypeId(), '%id' => $entity->id())
//            );


        } catch (EntityStorageException $e) {
            throw new HttpException(500, t('Internal Server Error'), $e);
        }
    }

    /**
     * Responds to entity PATCH requests.
     *
     * @Thruway(name = "update", type="procedure")
     *
     * @param \Drupal\Core\Entity\EntityInterface $original_entity
     *   The original entity object.
     *
     * @return array
     *
     */
    public function patch(EntityInterface $original_entity)
    {

        $entity = entity_load($original_entity->getEntityTypeId(), $original_entity->id());

        if (!$entity) {
            throw new BadRequestHttpException(t('Invalid entity'));
        }

        $definition = $this->getPluginDefinition();
        if ($entity->getEntityTypeId() != $definition['entity_type']) {
            throw new BadRequestHttpException(t('Invalid entity type'));
        }
        if (!$entity->access('update')) {
            throw new AccessDeniedHttpException();
        }

        // Overwrite the received properties.
        foreach ($original_entity as $field_name => $field) {
            if (isset($entity->{$field_name})) {
                // It is not possible to set the language to NULL as it is automatically
                // re-initialized. As it must not be empty, skip it if it is.
                // @todo: Use the langcode entity key when available. See
                //   https://drupal.org/node/2143729.
                if ($field_name == 'langcode' && $field->isEmpty()) {
                    continue;
                }
//                if ($field->isEmpty() && !$original_entity->get($field_name)->access('delete')) {
//                    throw new AccessDeniedHttpException(
//                        t('Access denied on deleting field @field.', array('@field' => $field_name))
//                    );
//                }
                $entity->set($field_name, $original_entity->getValue($field_name)[$field_name]);
                if (!$entity->get($field_name)->access('update')) {
                    throw new AccessDeniedHttpException(
                        t('Access denied on updating field @field.', array('@field' => $field_name))
                    );
                }
            }
        }

        // Validate the received data before saving.
        $this->validate($original_entity);
        try {
            $entity->save();
//            $this->logger->notice(
//                'Updated entity %type with ID %id.',
//                array('%type' => $entity->getEntityTypeId(), '%id' => $entity->id())
//            );

            // Update responses have an empty body.
            return $entity;
        } catch (EntityStorageException $e) {
            throw new HttpException(500, t('Internal Server Error'), $e);
        }
    }

    /**
     * Responds to entity DELETE requests.
     *
     * @Thruway(name = "remove", type="procedure")
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity object.
     *
     * @return array
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function delete(EntityInterface $entity)
    {
        if (!$entity->access('delete')) {
            throw new AccessDeniedHttpException();
        }
        try {
            $entity->enforceIsNew(false);
            $entity->delete();
//            $this->logger->notice(
//                'Deleted entity %type with ID %id.',
//                array('%type' => $entity->getEntityTypeId(), '%id' => $entity->id())
//            );

            // Delete responses have an empty body.
            return $entity;
        } catch (EntityStorageException $e) {
            throw new HttpException(500, t('Internal Server Error'), $e);
        }
    }


    /**
     * Responds to referencedEntities requests.
     *
     * @Thruway(name = "referencedEntities", type="procedure")
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity object.
     *
     * @return array
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function getReferencedEntities(EntityInterface $entity)
    {
        try {

            $entities = $this->referencedEntities($entity);
            $return = [];

            $resources = \Drupal::config('thruway.settings')->get('resources');

            foreach ($entities as $name => $entityTypes) {
                foreach ($entityTypes as  $entity) {
                    //Only return the entity if it's enabled in the thruway config
                    if (array_key_exists("entity:{$entity->getEntityTypeId()}", $resources)) {

                        if (!$entity->access('view')) {
                            throw new AccessDeniedHttpException();
                        }
                        foreach ($entity as $field_name => $field) {
                            if (method_exists($field, 'access') && !$field->access('view')) {
                                unset($entity->{$field_name});
                            }
                        }

                        $return["entity.{$entity->getEntityTypeId()}.{$entity->bundle()}"][$entity->uuid(
                        )][$name] = $entity;
                    }
                }
            }

            return [$return];
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
    }

    /**
     * Verifies that the whole entity does not violate any validation constraints.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity object.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *   If validation errors are found.
     */
    protected function validate(EntityInterface $entity)
    {
        $violations = $entity->validate();
        if (count($violations) > 0) {
            $message = "Unprocessable Entity: validation failed.\n";
            foreach ($violations as $violation) {
                $message .= $violation->getPropertyPath() . ': ' . $violation->getMessage() . "\n";
            }
            // Instead of returning a generic 400 response we use the more specific
            // 422 Unprocessable Entity code from RFC 4918. That way clients can
            // distinguish between general syntax errors in bad serializations (code
            // 400) and semantic errors in well-formed requests (code 422).
            throw new HttpException(422, $message);
        }
    }


    public function referencedEntities(EntityInterface $entity)
    {
        $referenced_entities = array();

        // Gather a list of referenced entities.
        foreach ($entity->getProperties() as $field_name => $field_items) {
            foreach ($field_items as $field_item) {
                // Loop over all properties of a field item.
                foreach ($field_item->getProperties(true) as $property) {
                    if ($property instanceof EntityReference && $referenced_entity = $property->getTarget()) {
                        $referenced_entities[$field_name][] = $referenced_entity;
                    }
                }
            }
        }

        return $referenced_entities;
    }
}
