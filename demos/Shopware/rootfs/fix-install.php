<?php

require_once '/var/www/html/vendor/autoload.php';

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\Exception\LanguageNotFoundException;

$params = parse_url(getenv('DATABASE_URL'));
$dbName = substr($params['path'], 1);

$dsnWithoutDb = sprintf(
    '%s://%s%s:%s',
    $params['scheme'],
    isset($params['pass'], $params['user']) ? ($params['user'] . ':' . $params['pass'] . '@') : '',
    $params['host'],
    $params['port'] ?? 3306
);

$parameters = [
    'url' => $dsnWithoutDb,
    'charset' => 'utf8mb4',
];

if (isset($_ENV['DATABASE_SSL_CA'])) {
    $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = $_ENV['DATABASE_SSL_CA'];
}

if (isset($_ENV['DATABASE_SSL_CERT'])) {
    $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CERT] = $_ENV['DATABASE_SSL_CERT'];
}

if (isset($_ENV['DATABASE_SSL_KEY'])) {
    $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_KEY] = $_ENV['DATABASE_SSL_KEY'];
}

if (isset($_ENV['DATABASE_SSL_DONT_VERIFY_SERVER_CERT'])) {
    $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

$connection = DriverManager::getConnection($parameters, new Configuration());
$connection->exec('USE `' . $dbName . '`');

class LocaleAndCurrencySwapper
{
    private $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function setDefaultCurrency(string $currency): void
    {
        $stmt = $this->connection->prepare('SELECT iso_code FROM currency WHERE id = ?');
        $stmt->execute([Uuid::fromHexToBytes(Defaults::CURRENCY)]);
        $currentCurrencyIso = $stmt->fetchColumn();

        if (!$currentCurrencyIso) {
            throw new \RuntimeException('Default currency not found');
        }

        if (mb_strtoupper($currentCurrencyIso) === mb_strtoupper($currency)) {
            return;
        }

        $newDefaultCurrencyId = $this->getCurrencyId($currency);

        $stmt = $this->connection->prepare('UPDATE currency SET id = :newId WHERE id = :oldId');

        // assign new uuid to old DEFAULT
        $stmt->execute([
            'newId' => Uuid::randomBytes(),
            'oldId' => Uuid::fromHexToBytes(Defaults::CURRENCY),
        ]);

        // change id to DEFAULT
        $stmt->execute([
            'newId' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'oldId' => $newDefaultCurrencyId,
        ]);

        $stmt = $this->connection->prepare(
            'SET @fixFactor = (SELECT 1/factor FROM currency WHERE iso_code = :newDefault);
             UPDATE currency
             SET factor = IF(iso_code = :newDefault, 1, factor * @fixFactor);'
        );
        $stmt->execute(['newDefault' => $currency]);
    }

    private function getCurrencyId(string $currencyName): string
    {
        $stmt = $this->connection->prepare(
            'SELECT id FROM currency WHERE LOWER(iso_code) = LOWER(?)'
        );
        $stmt->execute([$currencyName]);
        $fetchCurrencyId = $stmt->fetchColumn();

        if (!$fetchCurrencyId) {
            throw new \RuntimeException('Currency with iso-code ' . $currencyName . ' not found');
        }

        return (string) $fetchCurrencyId;
    }

    public function setDefaultLanguage(string $locale): void
    {
        $currentLocaleStmt = $this->connection->prepare(
            'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = ?'
        );
        $currentLocaleStmt->execute([Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        $currentLocale = $currentLocaleStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$currentLocale) {
            throw new \RuntimeException('Default language locale not found');
        }

        $currentLocaleId = $currentLocale['id'];
        $newDefaultLocaleId = $this->getLocaleId($locale);

        // locales match -> do nothing.
        if ($currentLocaleId === $newDefaultLocaleId) {
            return;
        }

        $newDefaultLanguageId = $this->getLanguageId($locale);

        if (!$newDefaultLanguageId) {
            $newDefaultLanguageId = $this->createNewLanguageEntry($locale);
        }

        if ($locale === 'de-DE' && $currentLocale['code'] === 'en-GB') {
            $this->swapDefaultLanguageId($newDefaultLanguageId);
        } else {
            $this->changeDefaultLanguageData($newDefaultLanguageId, $currentLocale, $locale);
        }
    }

    private function getLocaleId(string $iso): string
    {
        $stmt = $this->connection->prepare('SELECT locale.id FROM  locale WHERE LOWER(locale.code) = LOWER(?)');
        $stmt->execute([$iso]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new \RuntimeException('Locale with iso-code ' . $iso . ' not found');
        }

        return (string) $id;
    }

    private function getLanguageId(string $iso): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT language.id
             FROM `language`
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE LOWER(locale.code) = LOWER(?)'
        );
        $stmt->execute([$iso]);

        return $stmt->fetchColumn() ?: null;
    }

    private function createNewLanguageEntry(string $iso)
    {
        $id = Uuid::randomBytes();

        $stmt = $this->connection->prepare(
            '
            SELECT LOWER (HEX(locale.id))
            FROM `locale`
            WHERE LOWER(locale.code) = LOWER(?)'
        );
        $stmt->execute([$iso]);
        $localeId = $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            '
            SELECT LOWER(language.id)
            FROM `language`
            WHERE LOWER(language.name) = LOWER(?)'
        );
        $stmt->execute(['english']);
        $englishId = $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            '
            SELECT locale_translation.name
            FROM `locale_translation`
            WHERE LOWER(HEX(locale_id)) = ?
            AND LOWER(language_id) = ?'
        );
        //Always use the English name since we dont have the name in the language itself
        $stmt->execute([$localeId, $englishId]);
        $name = $stmt->fetchColumn();
        if (!$name) {
            throw new LanguageNotFoundException("locale_translation.name for iso: '" . $iso . "', localeId: '" . $localeId . "' not found!");
        }

        $stmt = $this->connection->prepare(
            '
            INSERT INTO `language`
            (id,name,locale_id,translation_code_id, created_at)
            VALUES
            (?,?,UNHEX(?),UNHEX(?), ?)'
        );

        $stmt->execute([$id, $name, $localeId, $localeId, (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        return $id;
    }

    private function swapDefaultLanguageId(string $newLanguageId): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE language
             SET id = :newId
             WHERE id = :oldId'
        );

        // assign new uuid to old DEFAULT
        $stmt->execute([
            'newId' => Uuid::randomBytes(),
            'oldId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ]);

        // change id to DEFAULT
        $stmt->execute([
            'newId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'oldId' => $newLanguageId,
        ]);
    }

    private function changeDefaultLanguageData(string $newDefaultLanguageId, array $currentLocaleData, string $locale): void
    {
        $enGbLanguageId = $this->getLanguageId('en-GB');
        $currentLocaleId = $currentLocaleData['id'];
        $name = $locale;

        $newDefaultLocaleId = $this->getLocaleId($locale);

        if (!$newDefaultLanguageId && $enGbLanguageId) {
            $stmt = $this->connection->prepare(
                'SELECT name FROM locale_translation
                 WHERE language_id = :language_id
                 AND locale_id = :locale_id'
            );
            $stmt->execute(['language_id' => $enGbLanguageId, 'locale_id' => $newDefaultLocaleId]);
            $name = $stmt->fetchColumn();
        }

        // swap locale.code
        $stmt = $this->connection->prepare(
            'UPDATE locale SET code = :code WHERE id = :locale_id'
        );
        $stmt->execute(['code' => 'x-' . $locale . '_tmp', 'locale_id' => $currentLocaleId]);
        $stmt->execute(['code' => $currentLocaleData['code'], 'locale_id' => $newDefaultLocaleId]);
        $stmt->execute(['code' => $locale, 'locale_id' => $currentLocaleId]);

        // swap locale_translation.{name,territory}
        $setTrans = $this->connection->prepare(
            'UPDATE locale_translation
             SET name = :name, territory = :territory
             WHERE locale_id = :locale_id AND language_id = :language_id'
        );

        $currentTrans = $this->getLocaleTranslations($currentLocaleId);
        $newDefTrans = $this->getLocaleTranslations($newDefaultLocaleId);

        foreach ($currentTrans as $trans) {
            $trans['locale_id'] = $newDefaultLocaleId;
            $setTrans->execute($trans);
        }
        foreach ($newDefTrans as $trans) {
            $trans['locale_id'] = $currentLocaleId;
            $setTrans->execute($trans);
        }

        $updLang = $this->connection->prepare('UPDATE language SET name = :name WHERE id = :language_id');

        // new default language does not exist -> just set to name
        if (!$newDefaultLanguageId) {
            $updLang->execute(['name' => $name, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);

            return;
        }

        $langName = $this->connection->prepare('SELECT name FROM language WHERE id = :language_id');
        $langName->execute(['language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        $current = $langName->fetchColumn();

        $langName->execute(['language_id' => $newDefaultLanguageId]);
        $new = $langName->fetchColumn();

        // swap name
        $updLang->execute(['name' => $new, 'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        $updLang->execute(['name' => $current, 'language_id' => $newDefaultLanguageId]);
    }

    private function getLocaleTranslations(string $localeId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT locale_id, language_id, name, territory
             FROM locale_translation
             WHERE locale_id = :locale_id'
        );
        $stmt->execute(['locale_id' => $localeId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

$fixer = new LocaleAndCurrencySwapper($connection);
$fixer->setDefaultCurrency(getenv('INSTALL_CURRENCY'));
$fixer->setDefaultLanguage(getenv('INSTALL_LOCALE'));
