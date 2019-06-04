# Slim4-Skeleton

Web application skeleton that uses the [Slim4 Framework](http://www.slimframework.com/), 
[PHP-DI](http://php-di.org/) as dependency injection container, [Nyholm PSR7](https://github.com/Nyholm/psr7) as PSR-7 implementation
and [Twig](https://twig.symfony.com/) as template engine.

This skeleton application was built for [Composer](https://getcomposer.org/).


## Installation

Run this command from the directory in which you want to install your new Slim4 Framework
application.

```bash
composer create-project adriansuter/slim4-skeleton [my-app-name]
```

Replace `[my-app-name]` with the desired directory name for your new application. 
You'll want to:

* Point your virtual host document root to your new application's `public/` directory.
* Ensure `cache/` is web writable.

To run the application in development, you can run these commands 

```bash
cd [my-app-name]
composer start
```

**That's it! Now go build something cool.**

## Getting up to speed

Glued comes with two microservices aimed at getting you up to speed quickly.
The `basic101` microservice serves as an introduction to the Slim4 framework.
It will help you to

- write a hello world (see /basic101/)
- render a basic twig view (see /basic101/twig)
- render a basic html view (see /basic101/html) - beware, unlike the twig renderer, you have to take care of XSS protection yourserlf
- setup a basic api endpoint (see /basic101/api)
- authenticate users (see /basic101/auth)
- work with files (see /basic101/storage)

The `skeleton` microservice is a copy&edit skeleton, which includes everything needed to write a proper microservice:

- setup
  - database migration
  - directory structure
- a json schema (form generation, data validation, combined document/sql storage)
- an authenticated REST api (list, get, set)
- a generated browser interface (list, get, set)







Glued comes with a microservice skeleton, which should give you enough guidance to


Usually