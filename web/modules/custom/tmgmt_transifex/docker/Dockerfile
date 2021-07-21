FROM drupal:8
RUN apt-get update
RUN apt-get install -y default-mysql-client
RUN curl https://getcomposer.org/installer | php
RUN php composer.phar require drush/drush:8.3.3
RUN ./vendor/drush/drush/drush dl tmgmt tmgmt_transifex views views_bulk_operations \
    rules locale_translation_context --y
COPY init.sh .
ENTRYPOINT ["sh", "init.sh"]
