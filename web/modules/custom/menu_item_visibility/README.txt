
CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration


INTRODUCTION
------------

This module allows you to restrict access of menu items per roles. It depends
on the drupal core menu.module - just activate both modules and edit a menu
item as usual.
There will be a new fieldset that allows you to restrict access by role.


REQUIREMENTS
------------

Core dependencies only.


INSTALLATION
------------

Install via the admin GUI or with:
drush en menu_item_visibility -y


CONFIGURATION
-------------

Edit a menu item as usual at /admin/structure/menu
There will be a fieldset that allows you to restrict access by role.

If you don't check any roles the default access permissions will be kept.
Otherwise the module will also restrict access to the chosen user roles.
