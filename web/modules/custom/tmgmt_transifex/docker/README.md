# To run Drupal8 with docker

First, `cd` into the docker folder. Then,

```sh
→ docker-compose build
→ docker-compose up
```

Then, go to localhost:5008 and log in using the `admin:admin` credentials. You
will still need to configure the plugin following the
[instructions](https://docs.transifex.com/drupal-integrations/drupal-8).

The plugin code from the repository will be available inside the container, so
feel free to work on the plugin.