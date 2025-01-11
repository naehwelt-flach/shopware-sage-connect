<?php /** @noinspection PhpInternalEntityUsedInspection */
declare(strict_types=1);

namespace Naehwelt\Shopware;

use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class DataService
{
    public function __construct(
        private SyncController $sync,
        private RequestStack $requestStack,
    ){}

    public function generate(Context $context): void
    {
        $req = self::req(ImportExportProfileDefinition::ENTITY_NAME, self::profiles());
        $this->requestStack->push($req);
        $res = $this->sync->sync($req, $context);
        $this->requestStack->pop();
        $result = json_decode((string) $res->getContent(), true);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException(\sprintf('Error importing: %s', \print_r($result, true)));
        }
    }

    private static function req(
        string $entityName,
        iterable $entities,
        string $action = SyncOperation::ACTION_UPSERT
    ): Request {
        $payload = [];
        foreach ($entities as $id => $entity) {
            if (!isset($entity['id'])) {
                $id = is_numeric($id) ? Uuid::fromStringToHex(json_encode($entity, JSON_THROW_ON_ERROR)) : $id;
                Uuid::isValid($id) || $id = Uuid::fromStringToHex($id);
                $entity['id'] = $id;
            }

            $payload[] = $entity;
        }
        return new Request([], [], [], [], [], [], \json_encode([[
            'action' => $action,
            'entity' => $entityName,
            'payload' => $payload,
        ]], JSON_THROW_ON_ERROR));
    }

    private static function profiles(): iterable
    {
        static $default = [
            'systemDefault' => false,
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
        ];
        $mapping = static function (iterable $mapping) {
            $i = 0;
            foreach ($mapping as $mappedKey => $key) {
                is_array($key) || $key = ['key' => $key];
                yield $key + [
                    'key' => $mappedKey,
                    'mappedKey' => $mappedKey,
                    'position' => $i++,
                ];
            }
        };
        yield 'sc_importexport_profile' => [
            'sourceEntity' => ImportExportProfileDefinition::ENTITY_NAME,
            'technicalName' => 'sc_' . ImportExportProfileDefinition::ENTITY_NAME,
            'label' => 'SageConnect-Profil Import/Export Profil',
            'mapping' => [...$mapping([
                'name' => ['key' => 'technicalName', 'requiredByUser' => true],
                'label' => 'translations.DEFAULT.label',
                'mapping' => 'mapping',
                'config' => 'config',
                'update_by' => 'updateBy',
                'source_entity' => 'sourceEntity',
                'file_type' => ['key' => 'fileType', 'useDefaultValue' => true, 'defaultValue' => 'text/csv'],
                'delimiter' => ['useDefaultValue' => true, 'defaultValue' => ';'],
                'enclosure' => ['useDefaultValue' => true, 'defaultValue' => '"'],
            ])],
            'updateBy' => [
                ['mappedKey' => 'technicalName', 'entityName' => ImportExportProfileDefinition::ENTITY_NAME],
            ],
            'config' => ['createEntities' => true, 'updateEntities' => true],
        ] + $default;
    }
}
