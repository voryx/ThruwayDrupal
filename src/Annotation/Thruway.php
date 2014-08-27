<?php

/**
 * @file
 * Contains \Drupal\thruway\Annotation\Thruway.
 */

namespace Drupal\thruway\Annotation;


/**
 * Defines a WAMP resource annotation object.
 * All names will be prepended with resource id.  ie. entity:node.add
 *
 * @Annotation('Method')
 */
class Thruway {

  /**
   * The name of the RPC call or subscription
   *
   * @var string
   */
  public $name;

  /**
   * The type: procedure or subscribe
   *
   * @var string
   */
  public $type;

  /**
   * Override the resource serialization class
   *
   * @var
   */
  public $serialization_class;

}
