<?php

/**
 * @file
 * Contains \Drupal\thruway\Annotation\ThruwayResource.
 */

namespace Drupal\thruway\Annotation;

use \Drupal\Component\Annotation\Plugin;

/**
 * Defines a WAMP resource annotation object.
 *
 * Plugin Namespace: Plugin\thruway\resource
 *
 * @see plugin_api
 *
 * @ingroup third_party
 *
 * @Annotation
 */
class ThruwayResource extends Plugin {

  /**
   * The resource plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the resource plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

}
