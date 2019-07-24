# GraphQL

[![Build](https://badgen.net/travis/typo3-initiatives/graphql)](https://travis-ci.com/TYPO3-Initiatives/graphql)
[![Coverage](https://badgen.net/codacy/coverage/052bb2cd84cb461a92b172c1953989b4)](https://app.codacy.com/project/TYPO3-Initiatives/graphql/dashboard)
[![Code Quality](https://badgen.net/codacy/grade/052bb2cd84cb461a92b172c1953989b4)](https://app.codacy.com/project/TYPO3-Initiatives/graphql/dashboard)

This extension integrates GraphQL into TYPO3 CMS. Currently it provides an read API for managed tables. For more information about the planned features see the [draft](https://docs.google.com/document/d/1M-V9H9W_tmWZI-Be9Zo5xTZUMgwJk2dMUxOFw-waO04/).

*This implementation is a proof-of-concept prototype and thus experimental development. Since not all planned features are implemented, this extension should not be used for production sites.*

## Installation

Use composer to install this extension in your project:

```bash
composer config repositories.cms-configuration git https://github.com/typo3-initiatives/configuration
composer config repositories.cms-security git https://github.com/typo3-initiatives/security
composer config repositories.cms-graphql git https://github.com/typo3-initiatives/graphql
composer require typo3/cms-graphql
```

## Usage

The *entity reader* provides an easy access to the managed tables of TYPO3 CMS:

```php
use TYPO3\CMS\GraphQL;

$reader = new EntityReader();
$result = $reader->execute('
    tt_content {
        uid,
        header,
        bodytext
    }
');
```

For more examples checkout the [functional tests](Tests/Functional/GraphQL/EntityReaderTest.php).

## Development

Development for this extension is happening as part of the [TYPO3 persistence initiative](https://typo3.org/community/teams/typo3-development/initiatives/persistence/).
