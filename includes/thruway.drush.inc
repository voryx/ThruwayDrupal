<?php

/**
 * implements hook_drush_command
 * @return array
 */
function thruway_drush_command()
{
    $items = array();

    $items['thruway-client'] = array(
        'description' => "Thruway Client",
        'examples' => array(
            'drush thruway' => 'Start the Thruway client',
        ),
        'aliases' => array('thruway'),
    );

    return $items;
}


function drush_thruway_client()
{
    /* @var $connection \Drupal\thruway\Connection */
    $connection = \Drupal::service('thruway.connection');

    $auth = false;
    $authentication = \Drupal::config('thruway.settings')->get('authentication');
    if (isset($authentication['enable_auth']) && $authentication['enable_auth'] === true) {
        $auth = true;
    }

    $connection->open($auth);
}
