# Nashor Scraper
[![Latest Stable Version](https://poser.pugx.org/pewpewyou/nashor-scraper/v/stable)](https://packagist.org/packages/pewpewyou/nashor-scraper)
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]](https://travis-ci.org/pewpewyou/nashor-scraper.svg?branch=master)
[![Total Downloads](https://poser.pugx.org/pewpewyou/nashor-scraper/downloads)](https://packagist.org/packages/pewpewyou/nashor-scraper)

This is a open source multi-thread League of Legends scraper for OP.GG website.

## Structure

If any of the following are applicable to your project, then the directory structure should follow industry best practises by being named the following.

```
bin/        
config/
src/
tests/
vendor/
```


## Install

This package can be found on Packagist and is best loaded using Composer.

``` bash
$ composer require pewpewyou/nashor-scraper
```

## Usage

``` php

use Nashor\Summoner;

$summoner = new Summoner('scorpionp', 3551110, 'lan');
$summoner->mmr();
$summoner->profile();

```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing
TBA
``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email :author_email instead of using the issue tracker.

## Credits

- [Ernest Hernandez](http://ernest.gallery)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/:vendor/:package_name.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/:vendor/:package_name/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/:vendor/:package_name.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/:vendor/:package_name.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/:vendor/:package_name.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/:vendor/:package_name
[link-travis]: https://travis-ci.org/:vendor/:package_name
[link-scrutinizer]: https://scrutinizer-ci.com/g/:vendor/:package_name/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/:vendor/:package_name
[link-downloads]: https://packagist.org/packages/:vendor/:package_name
[link-author]: https://github.com/:author_username
[link-contributors]: ../../contributors
