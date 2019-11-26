# GLued-Skeleton

A full blown modular webapp skeleton built around

* [Slim4 Framework](http://www.slimframework.com/), 
* [PHP-DI](http://php-di.org/) as dependency injection container, 
* [Nyholm PSR7](https://github.com/Nyholm/psr7) as PSR-7 implementation,
* [Twig](https://twig.symfony.com/) as template engine,
* [Twig-assets](https://github.com/odan/twig-assets) as assets cache,
* [Twig-translation](https://github.com/odan/twig-translation) as a twig translator,
* Foxy as the asset manager
* Monolog
* mysqli-database-class
* and others

This skeleton application was built for [Composer](https://getcomposer.org/).


## Installation

Run this command from the directory in which you want to install your new Slim4 Framework application.

```bash
composer create-project vaizard/glued-skeleton  [my-app-name]
```

Replace `[my-app-name]` with the desired directory name for your new application. You'll want to:

* Point your virtual host document root to your new application's `public/` directory.
* Ensure `public/cache/` and `private/cache/*` is web writable.

To run the application in development, you can run these commands 

```bash
cd [my-app-name]
composer start
```

**That's it! Now go build something cool.**

## Develop

### Intro

Glued comes with two modules aimed at getting you up to speed quickly.
The `tutorial` microservice serves as an introduction to the Slim4 framework.

The `skeleton` module is a copy&paste&edit skeleton, which includes everything needed to write a proper microservice:

- setup (database migrations, folders, etc.)
- a json schema (form generation, data validation, combined document/sql storage)
- an authenticated REST api (list, get, set)
- a generated browser interface (list, get, set)

### Database migrations

Glued comes with phinx-migrations, which generates automatically database migration files. What this means is, that if you add a table, drop a column or do anything else to the structure of your database, a migration file will be generated to just automatically upgrade everyone's databases as well. Just run

```
./vendor/bin/phinx-migrations generate -e production --name initial
```

### Assets

Assets are managed (kept in an up-to-date state) by the composer foxy/foxy package. This package uses yarn (and a package.json) file to grab all that css and javascript for you and build it. Furthermore, odan/twig-assets will cache minified and joined versions of assets to give you a hassle free assets experience.

**WARNING:** Node packages are a hell. Some users report an installation failure. If this applies to you, just re-run `composer install`.


## Translations

Setting up odan/twig-translation is not trivial. This section intends to document the setup to save feature developers some frustration.

Translations code appears in:

* glued/Core/Bin/parse-twig.php
* glued/Core/Middleware/LocaleSessionMiddleware.php
* glued/Core/Middleware/TranslatorMiddleware.php
* glued/Core/middleware.php
* glued/Core/settings.php
* glued/Core/container.php
* glued/Core/bin/Includes/translation.php (included via a composer.json entry)

Usage:

* Run `apt install poedit` or equivalent on your distro
* Start Poedit and 
   * either open the file ./private/locale/*.po (or equivalent to `$settings['locale']['path']`)
   * or click "New" to add a new po file to ./private/locale
* Open menu: Catalog > Properties > Source Path
* Add source path: ./private/cache/twig (or equivalent to `$settings['twig']['cache']`) - yep, translations are generated from cache files (possibly to get the php code equivalent)
* Open tab: Sources keywords, Add keyword: \__, Click 'Ok' to store the settings
* Click button 'Update form source' to extract the template strings.
* Translate the text and save the file.
* Run `php glued/Core/Bin/parse-twig.php`

Credit for the initial translation implementation goes to https://github.com/odan/slim4-skeleton/commit/ba57560e33e379fd3ce6ef7ec09b68b029fb64ad

## Spider module

The spider module requires to do

```
curl -sL https://deb.nodesource.com/setup_12.x | sudo -E bash -
sudo npm install --global --unsafe-perm puppeteer
sudo chmod -R o+rx /usr/lib/node_modules/puppeteer/.local-chromium
```
