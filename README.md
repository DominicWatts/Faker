# Faker extension #

Magento 2 extension to generate fake data.

This extension uses `fzaninotto/faker`

Docs for this package can be found here: https://packagist.org/packages/fzaninotto/faker

# Install instructions #

`composer require dominicwatts/faker`

`php bin/magento setup:upgrade`

# Usage instructions #

Content is generated via the following console commands

## Generate Customer ##

`xigen:faker:customer [-w|--website WEBSITE] [-l|--limit [LIMIT]] [--] <generate>`

php/bin magento xigen:faker:customer generate

php/bin magento xigen:faker:customer -w 1 generate

php/bin magento xigen:faker:csutomer -w 1 -l 10 generate

## Generate Product ##

### todo: link configurable/group to simples ###

`xigen:faker:product [-w|--website WEBSITE] [-l|--limit [LIMIT]] [-t|--type [TYPE]] [--] <generate>`

php/bin magento xigen:faker:product generate

php/bin magento xigen:faker:product -w 1 generate

php/bin magento xigen:faker:product -w 1 -l 10 generate

php/bin magento xigen:faker:product -w 1 -l 10 -t simple generate

## Generate Category ##

`xigen:faker:category [-s|--store STORE] [-l|--limit [LIMIT]] [--] <generate>`

php/bin magento xigen:faker:category generate

php/bin magento xigen:faker:category -s 0 generate

php/bin magento xigen:faker:category -s 0 -l 10 generate

## Generate Order ##

`xigen:faker:order [-s|--store STORE] [-l|--limit [LIMIT]] [--] <generate>`

php/bin magento xigen:faker:order generate

php/bin magento xigen:faker:order -s 1 generate

php/bin magento xigen:faker:order -s 1 -l 1 generate
