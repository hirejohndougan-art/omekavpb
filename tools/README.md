# tools/

`npm run lint:php` expects the PHP-CS-Fixer tool here as `php-cs-fixer.phar`
(not included in the kit, to keep it small). Download it once:

```bash
curl -sSL -o tools/php-cs-fixer.phar https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar
```

The rules in `../.php-cs-fixer.dist.php` are Omeka S's own (from the omeka-s
repository), with the cache turned off.
