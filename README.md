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

## Microservices (modules)

### Core

The glue that keeps stuff together in Glued. To all microservices, core provides:

- user management 
  - authentication (sessions, jwt)
  - authorization (RBAC + attributes)
- content-addressable file storage (CAS)
- internationalization
- assets management and caching
- (some of the) security (see https://securityheaders.com/)
  - CSRF prevention (session cookie) via the SessionMiddleware;
  - TODO / CSRF prevention (other cookies) - hack this https://github.com/selective-php/samesite-cookie/blob/master/src/SameSiteCookieMiddleware.php
  - CSP prevention
  - XSS prevention
  - TODO unify header generation with someting like a middleware using https://github.com/BePsvPT/secure-headers

### Spider

A microservice to crawl websites, track changes and archive and search content.

Install requirements:

```
curl -sL https://deb.nodesource.com/setup_12.x | sudo -E bash -
sudo apt-get install -y nodejs gconf-service libasound2 libatk1.0-0 libc6 libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgcc1 libgconf-2-4 libgdk-pixbuf2.0-0 libglib2.0-0 libgtk-3-0 libnspr4 libpango-1.0-0 libpangocairo-1.0-0 libstdc++6 libx11-6 libx11-xcb1 libxcb1 libxcomposite1 libxcursor1 libxdamage1 libxext6 libxfixes3 libxi6 libxrandr2 libxrender1 libxss1 libxtst6 ca-certificates fonts-liberation libappindicator1 libnss3 lsb-release xdg-utils wget
sudo npm install --global --unsafe-perm puppeteer
sudo chmod -R o+rx /usr/lib/node_modules/puppeteer/.local-chromium
```

## Developers

### Database

**Concepts**

 Glued relies heavily on features available in MySQL 8. Adding Support for any other database software (or older MySQL versions) is likely to be quite painful. The main concepts of SQL development in glued are the following:

 - combine traditional relational SQL with JSON documents, take advantage of virtual columns (generated from the JSON docs)
 - use the utf8mb4 encoding by default
 - use foreign keys and constrains where needed (if you need to scale, just split off the most used tables into a different database and reimplement constrainst application side - its not that much work to do)
 - use https://github.com/ThingEngineer/PHP-MySQLi-Database-Class to handle MySQL safely (prototyping with rawQuery() is ok, but make sure to rewrite using the class methods to ensure an equal level of security across the codebase)

**Migrations**

Glued comes with phinx-migrations, which generates automatically database migration files. What this means is, that if you add a table, drop a column or do anything else to the structure of your database, a migration file will be generated to just automatically upgrade everyone's databases as well. Just run

```
./vendor/bin/phinx-migrations generate -e production --name initial
```



### Assets

Assets are managed (kept in an up-to-date state) by the composer foxy/foxy package. This package uses yarn (and a package.json) file to grab all that css and javascript for you and build it. Furthermore, odan/twig-assets will cache minified and joined versions of assets to give you a hassle free assets experience.

**WARNING:** Node packages are a hell. Some users report an installation failure. If this applies to you, just re-run `composer install`.


### Internationalization (i18n) / Translations

Always use translation functions when generating output. In php use the preconfigured symfony/translation package (Core\Includes\translation.php included via composer everywhere) with
echo __('Hello world'); In twig templates use the preconfigured odan/twig-translation, which uses the symfony/translation package to extend the twig templating engine with {{ __('Hello world') }}.

**Updating translations**

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


**Updating the translations code**

Integrating odan/twig-translation was not trivial. This section intends to document the setup to save feature developers some frustration when code updates are needed. Translations code appears in:

* glued/Core/Bin/parse-twig.php
* glued/Core/Middleware/LocaleSessionMiddleware.php
* glued/Core/Middleware/TranslatorMiddleware.php
* glued/Core/middleware.php
* glued/Core/settings.php
* glued/Core/container.php
* glued/Core/bin/Includes/translation.php (included via a composer.json entry)

Credit for the initial translation implementation goes to https://github.com/odan/slim4-skeleton/commit/ba57560e33e379fd3ce6ef7ec09b68b029fb64ad

### Validation and exceptions handling

Glued 

- relies on respect/validation, extends it through the validation class. 
- uses custom exceptions to display error messages on URIs
  throw new HttpNotFoundException($this->request, 'User not found');
- uses flash messages
- json data validation

Exception handling is done via 

- flash messages
- exception handlers
- api responses

**NOTE:** Don't forget to do i18n!

### Debuging

Remember that `var_dump($some_variable); die();` is your best friend.


### Developer tutorials

Glued comes with two modules aimed at getting you up to speed quickly.
The `tutorial` microservice serves as an introduction to the Slim4 framework.

The `skeleton` module is a copy&paste&edit skeleton, which includes everything needed to write a proper microservice:

- setup (database migrations, folders, etc.)
- a json schema (form generation, data validation, combined document/sql storage)
- an authenticated REST api (list, get, set)
- a generated browser interface (list, get, set)

