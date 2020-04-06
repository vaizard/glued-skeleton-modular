# Glued-Skeleton

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


### From github (on apache or nginx)

* Run `git clone https://github.com/vaizard/glued-skeleton` in `/var/www/html`
* Point your virtual host document root to `/var/www/html/glued-skeleton/public`
* Run `composer update`

### With composer

* Run `composer create-project vaizard/glued-skeleton [my-app-name]`
* Point your virtual host document root to your new application's `public/` directory.
* Ensure `public/cache/` and `private/cache/*` is web writable.


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
  - XSS prevention via
    - CSP (content security policy) middleware
    - output filtering
      - the whole Core/Views/templates/default.twig is wrapped in `{% autoescape %}{% endautoescape %}`
      - twig.js always initialized with `autoescape: true`
    - input filtering
      - see Core/Middleware/AntiXSSMiddleware.php
  - TODO unify header generation with someting like a middleware using https://github.com/BePsvPT/secure-headers

**Caching**

Glued's default cache headers (see `/public/.htaccess`) allow short term assets caching. Please understand that performance gains based on caching always comes at the cost of security. Sensitive data, i.e. pdf files, or private photos will get cached (depending on configuration locally by the user's browser or on public proxies). While html caching is disabled out of the box, if you want full security, completely disable caching (no-cache on everything). 
For the above mentioned security considerations, when sessions are on, php sets `Pragma: no-cache` by default on the generated output. Don't change this unless you know what you are doing.

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

### Frontend vs. Backend

The backend is purely API based. Developing the backend requires to use the Core\Classes\JsonResponse\JsonResponseBuilder class. The class features methods such as

- build() - to be called last, returns the json response
- withCode($code) - adds a response code item to the json response
- withValidationError($array) - adds data validation errors
- withValidationReseed($array) - adds the submitted data
- withData($array, $code = 200) - adds response data
- HATEOAS functions such as withPagination(), withEmbeds(), or withLinks()
- and others.

The frontend is built around 

- php-based twig templating that 
  - act as page scaffolding
  - perform some auth related changes to rendered pages
  - ensure i18n/l10n
- js-based twig templating (via twig.js), which
  - renders forms to perform post/put/patch/delete requests
  - re-renders the forms on errors (adds validation errors and data reseed)
  - renders data (received as json from the API)
- simple js renderer/ajax xhr requestor

NOTE: Browsers support only GET/POST methods, but the backend api uses the the whole package (GET/POST/DELETE/PATCH/PUT). To circumvent this limitation, forms are submitted either with the `X-Http-Method-Override` header or the `_method` hidden input field. The MethodOverrideMiddleware then takes care of modifying the request before Slim's router decides what to do with the request. To set the headers, use something like

```js
 $("#my-form").ajaxSubmit({
    headers: {
        "X-Http-Method-Override": "PUT"
    },         
```

(see docs for @claviska/jquery-ajax-submit for details).

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
./vendor/bin/phinx-migrations generate -e production --name initial --overwrite
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

### Validation & exceptions and error handling

Used technology

- `Respect\Validation` class, extended via `Core\Classes\Validation` class and `ValidationFormsMiddleware` which simplify handling validation failures of posted form data
- `Slim\Flash` to render feedback on actions
- Json data validation on API routes
- Choice between the `Whoops` and `Error` middlewares to display errors (hint: use whoops for development, error for production)

Practical usage

- Distinguish between infrastructure specific exceptions (e.g. HttpNotFoundException, HttpBadRequestException) and domain specific exceptions (e.g. DomainException, UnexpectedValueException, ValidationException, etc…).
- Use domain specific exception in classes (where glued handles the data internally only), rethrow the domain specific exceptions as infrastructure specific exceptions in controllers (set the return code depending on context, emit a friendlier/more readable error message). See how `Glued\Core\Controllers\AccountsController::read()` rethrows exceptions comming from `Glued\Core\Classes\Auth\Auth::user_read()`.
- Using infrastructure specific exceptions in classes is unwanted, since throwing them requires the Request passed as a parameter (i.e. `throw new HttpNotFoundException($this->request, 'User not found');`).
- Perform validation in classes. Optionally you can also validate in controllers, if it's usefull (i.e. see `Glued\Core\Controllers\AccountsController::signup_post()` where the `$this->validator` uses the container-residing `Core\Classes\Validation` helper that re-fills the signup form and explains which data is invalid)
- Don't forget to do i18n via. the `__()` function available both in glued's php sources and in its twig templates on error messages.

Notes

- In classes throwing exceptions is limited to cases which require a total execution stop (i.e. security concerns). I.e. in the `core/classes/auth/auth.php` class, exceptions are thrown only on invalid data in the `response()` function. Since the data fed to this function should be always valid (passed from the session data which the user cannot tamper with), getting invalid data here would indicate a serious problem. Other functions in this class don't throw exceptions and just return i.e. an empty result set.

### Debuging

Remember that `var_dump($some_variable); die();` is your best friend. Also use the `Whoops` error middleware (see settings.php)


### Developer tutorials

Glued comes with two modules aimed at getting you up to speed quickly.
The `tutorial` microservice serves as an introduction to the Slim4 framework.

The `skeleton` module is a copy&paste&edit skeleton, which includes everything needed to write a proper microservice:

- setup (database migrations, folders, etc.)
- a json schema (form generation, data validation, combined document/sql storage)
- an authenticated REST api (list, get, set)
- a generated browser interface (list, get, set)

