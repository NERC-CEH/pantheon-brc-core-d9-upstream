CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Maintainers


INTRODUCTION
------------

This module provides icons for language links, both for the Language switcher
block and (optionally) for node links.

It is a spin-off from Internationalization (i18n) package.

The default icons provided are PNG images with a fixed height of 12 pixels
and a variable width per the official dimension of the respective flag.
However this module can handle other image types and sizes too.

* For a full description of the module, visit the project page:
  https://www.drupal.org/project/languageicons

* To submit bug reports and feature suggestions, or to track changes:
  https://www.drupal.org/project/issues/languageicons


REQUIREMENTS
------------

This module requires Drupal 8 or higher.


INSTALLATION
------------

1. Install the module with Composer:
    ```
    $ composer require drupal/languageicons
    ```

2. Go to admin/extend. Install the module.

See [Installing modules](https://www.drupal.org/node/1897420) for further
information.


CONFIGURATION
-------------

1. Configuring the 'Language switcher' block, Go to admin/structure/block.

2. Ensure that 'Language switcher' block is associated with a visible region. If
   unsure, move the 'Language switcher' block to 'Sidebar first' region.

3. Click on 'Save blocks' button.

4. To preview simply view any appropriate page. If successful you will see a
   flag on the left side of each language link.

5. There are some configuration options at admin/config/regional/language/icons.
   You can place flags before or after the language link or choose to only
   display the language flag without the language name (pick "Replace link"
   under icon placement to do so). There are some other options so make sure to
   check it out.


MAINTAINERS
-----------

Current maintainers:
* Pieter Frenssen (pfrenssen) (https://www.drupal.org/u/pfrenssen)
* Freso (https://www.drupal.org/u/freso)
