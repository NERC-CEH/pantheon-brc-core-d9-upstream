FROM drupal:9
RUN apt-get update
RUN apt-get install -y default-mysql-client
RUN curl https://getcomposer.org/installer | php
RUN php composer.phar require drush/drush:10.5.0
RUN composer require 'drupal/tmgmt'
RUN composer require 'drupal/views_bulk_operations'

COPY init.sh .
ENTRYPOINT ["sh", "init.sh"]
