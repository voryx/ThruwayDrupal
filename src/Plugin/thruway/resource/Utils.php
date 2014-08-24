<?php
/**
 * Created by PhpStorm.
 * User: daviddan
 * Date: 8/18/14
 * Time: 10:31 PM
 */

namespace Drupal\thruway\Plugin\thruway\resource;

use Drupal\thruway\Annotation\Thruway;
use Drupal\thruway\Annotation\ThruwayResource;

/**
 * Represents entities as resources.
 *
 * @ThruwayResource(
 *   id = "utils",
 *   label = @Translation("Entity"),
 *   serialization_class = "Drupal\Core\Entity\Entity"
 * )
 */
class Utils
{

    /**
     * @Thruway(name = "clear_cache", type="procedure")
     */
    public function clearCache()
    {
        drupal_flush_all_caches();
    }

} 