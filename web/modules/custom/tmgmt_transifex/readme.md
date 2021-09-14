# Localizing your Drupal site with Transifex
Transifex’s Drupal integration lets you translate your Drupal site using Transifex. It works as an add on to [Translation Management Tool (TMGMT)](https://www.drupal.org/project/tmgmt), the de-facto Drupal module for localization backed by Microsoft, Acquia, and others.

This guide will walk you through the process of setting up TMGMT, configuring the [Transifex connector](https://www.drupal.org/project/tmgmt_transifex), sending content from Drupal to Transifex, and getting translations back in an automated way.

## Development

For local development you can use provided docker-compose file.

## Releasing

In order to release a new version of the project to drupal.org and make it available to
Drupal users do the following:

* From inside this repo add a drupal origin like so:
```
  git remote add drupal git@git.drupal.org:project/tmgmt_transifex.git
```

* Checkout the 8.x-1.x branch

* Push your local branch to drupal.org
```
  git push drupal 8.x-1.x
```
* Create a tag and push it to drupal.org
```
  git tag 8.x-0.9
  git push drupal tag 8.x-0.9
```
* Go to https://www.drupal.org/project/tmgmt_transifex, login and click edit
* Go to the releases tab and click on ```Add new release``` and publish your new version
* Tick the option ```This release will not be covered for security advisories``` and then press ```next```

## Installation and configuration
### Installing TMGMT

Before you can install the Transifex connector, you will need to install the TMGMT module along with its dependencies. Here’s how:

1. Download and install the TMGMT module for Drupal 8.

2. As an admin, go to the modules page, then under `Translation Management` enable the
  `Translation Management Core`, `Config Entity Source` and `Content Entity Source`
   modules as well as all 4 modules under the `Multilingual` category.
   TMGMT comes with other modules as well, feel free to add any additional ones you may
   need depending on your needs.

   If successful, you should see a `Translation` submenu appear in the top admin bar.


### Configuring languages for your Drupal site

Next, you can add the languages that you want your site translated to:


1. From the main navigation, head to **Configuration**. Then under **REGIONAL AND LANGUAGE**, click on **Languages**.
2. Click on **+Add language**.
3. Select a language from the list and then click **Add language**.
4. Repeat Steps 2 and 3 to add all the languages you wish to translate your Drupal site to.

### Configuring Translation sources

After installing TMGMT, you need to denote certain content types that will be translatable. To do that go to **Configuration >
Regional and language > Content language** and check the category types that you want to be translatable in your site. In the
default Drupal installation, we recommend to have at least checked the `Content`, `Custom block` and `Custom menu links` ones.

When clicking each of the above, you will be presented with a list of content types for each category (ex `Article`,
`Basic page` etc). You should check the attributes that you want to be translatable for each content type, then save the page.


### Installing Transifex Connector

In order to import content to Transifex you need to download, install and enable the Transifex Connector.
From **Translation > Providers** select the Transifex Translator and fill the configuration form with your credentials.


At this stage you have two choices regarding your localization workflow. You can either consider translations coming from Transifex as ready to be imported either if they are 100% translated or 100% reviewed. So if you want also a review step to your process enable the Pull resources settings so translations get imported only when they are 100% reviewed.


To create a file-based project for use with the drupal integration go to → https://www.transifex.com/your-organization/add/. If you also want to add a webhook, go to your project’s dashboard select Settings > Webhooks and set your URL target to be http://<your drupal instance>/tmgmt_transifex_callback and also set event to **Any Event.**    


You will also need an API token for Transifex that can be collected from https://www.transifex.com/user/settings/.

## Usage

### Importing content  

Open the Translation submenu at the top of your admin bar and select the Sources tab, then click the content that you want to make available for translation.


From there you can either add them to the cart or request their translation. At the request translation menu you can review the contents of your order, set the jobs label and set the target language. You will need one translation job for each target language.


By clicking submit to translator, the plugin will create matching resources for each node at Transifex.  You can see your strings by going to your Transifex dashboard.

### Pulling translations

There are two ways to retrieve translated content back from Transifex.


- **Webhooks:** In that case when a resource **review** in Transifex reaches 100% a webhook is submitted targeted towards your Drupal installation.  When the webhook is received by the Transifex Connector it will query Transifex in order to retrieve the translations and uses TMGMT in order to apply the translations for the site.  In order to set up webhooks you first need to configure them in Transifex’s side by going to <org>/<project>/settings/webhooks/ and adding a webhook that points to:  

```
     http://<your-drupal-instance>/tmgmt_transifex_callback
     ```

  *The translations are auto-accepted so whenever a resource reaches 100% in Transifex its automatically updated in Drupal.*


- **Manual**: By using the Translate submenu, go to the jobs tab and click on the job you want to check for translations.


The module will first check if the resource has reached 100% translation and if its true then its going to import translations inside Drupal.
