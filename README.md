composer-netrc-auth-plugin
==========================

composer-netrc-auth-plugin is a [composer plugin](https://getcomposer.org/doc/articles/plugins.md) that provides netrc-based HTTP authorization during dist's
downloading process using custom VCS drivers.

[![Build Status](https://travis-ci.org/fduch/composer-netrc-auth-plugin.svg?branch=master)](https://travis-ci.org/fduch/composer-netrc-auth-plugin)

About plugin
------------

Sometimes it is usefull to work with custom VCSDriver to handle private packages
via packagist behind custom git server (not github, not bitbucket - they are well supported by composer) that generates
dist's, for example gitolite (see github [discussion](https://github.com/composer/packagist/issues/389) for details).
In this case there is problem with authorization during automated packages installations via composer
(this is important on build, CI servers, etc).
The cause of this problem is that composer uses external *git* process (it could be another *vcs*-process)
to fetch sources and regular file transfer mechanisms (such as *file_get_contents*) to fetch archives.
There are some features in git core that provides automatic authorization - netrc parsing, *askpass* feature, so
the sources can be fetched automatically. But dist's downloading leads authorization console promt if git server
requires authorization.
This plugin extends composer functionality with netrc-based authorization during archives downloading process.

Installation
------------
In order to install plugin on system-wide level use composer global command
```sh
php composer.phar global require fduch/composer-netrc-auth-plugin
```

Also you can use plugin inside your package locally by requiring it as a regular package:
```sh
php composer.phar require fduch/composer-netrc-auth-plugin
```

See more about plugin installation in [official documentation](https://getcomposer.org/doc/articles/plugins.md#using-plugins)
