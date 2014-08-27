Thruway for Drupal
===========

Is a module that brings real time object synchronization and [Thruway](https://github.com/voryx/Thruway) integration to Drupal 8.


### Installation

This project depends on a couple of external libraries.  The easiest way to install them is with [drush](https://github.com/drush-ops/drush) and [composer](https://getcomposer.org/).

1. Download Composer [more info](https://getcomposer.org/doc/00-intro.md#downloading-the-composer-executable)

      $ curl -sS https://getcomposer.org/installer | php

2. Install the latest version of Drush 7

      $ sudo php composer.phar global require drush/drush:dev-master

3. Make sure that the composer bin folder has been added to the system path. (update your .bashrc or .profile)

      $ export PATH="$HOME/.composer/vendor/bin:$PATH"

4. Install composer support for drush

      $ drush en composer

5. Switch to your project's folder

6. Install required libraries

      $ drush composer require "ratchet/pawl":"dev-master"

      $ drush composer require "voryx/thruway":"dev-master"

      $ drush composer require "firebase/php-jwt":"dev-master"

7. Copy the Thruway module to your modules folder

8. Enable the Thruway module

      $ drush en thruway

9. Start the Thruway Client

      $ drush thruway


The default configuration will expose entity:node:page, entity:node:article and entity:taxonomy_term:tags

