# 1. Wait for database connection

for i in `seq 10` true; do
    mysqladmin ping \
        --host=mysql_drupal8 \
        --user=user \
        --password=password > /dev/null 2>&1
    result=$?
    if [ $result -eq 0 ] ; then
        break
    fi
    sleep 1
done

# 2. If site not already installed, install site and enable plugins

( ./vendor/drush/drush/drush status bootstrap | grep -q Successful ) || \
    ./vendor/drush/drush/drush -y site-install \
        --db-url=mysql://user:password@mysql_drupal8:3306/drupal \
        --account-name=admin \
        --account-pass=admin \
        standard \
        install_configure_form.update_status_module=NULL


# 3. Disable asset preprocessing
drush -y config-set system.performance css.preprocess 0
drush -y config-set system.performance js.preprocess 0

# 4. Enable drupal plugins

./vendor/drush/drush/drush -y en \
    tmgmt_transifex tmgmt_content tmgmt_locale views views_bulk_operations \
    rules locale_translation_context

# 5. Run default entrypoint

docker-php-entrypoint apache2-foreground
