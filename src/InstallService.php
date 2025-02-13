<?php /** @noinspection PhpInternalEntityUsedInspection */
declare(strict_types=1);

namespace Naehwelt\Shopware;

use Naehwelt\Shopware\ImportExport\DirectoryHandler;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class InstallService
{
    private string $profileProfile;

    public function __construct(
        private SyncController $sync,
        private RequestStack $requestStack,
        private DirectoryHandler $directoryHandler,
    ) {
        $this->profileProfile = SageConnect::id('_' . ImportExportProfileDefinition::ENTITY_NAME . '_yaml');
    }

    public function createImportExportProfileEntity(Context $context): void
    {
        $req = self::req(ImportExportProfileDefinition::ENTITY_NAME, $this->profiles());
        $this->requestStack->push($req);
        $res = $this->sync->sync($req, $context);
        $this->requestStack->pop();
        $result = json_decode((string) $res->getContent(), true);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException(\sprintf('Error importing: %s', \print_r($result, true)));
        }
    }

    public function importResources(Context $context): void
    {
        $handler = $this->directoryHandler->with(
            location: 'profiles',
            processFactory: ['profileCriteria' => ['technicalName' => $this->profileProfile]]
        );
        $handler($context);
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

    private function profiles(): iterable
    {
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
        yield $this->profileProfile => [
            'name' => $this->profileProfile,
            'technicalName' => $this->profileProfile,
            'systemDefault' => false,
            'fileType' => 'application/x-yaml',
            'sourceEntity' => ImportExportProfileDefinition::ENTITY_NAME,
            'label' => 'SageConnect-Profil Import/Export Profil (YAML)',
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
            // unused, but required
            'delimiter' => ';',
            'enclosure' => '"',
        ];
    }
}
