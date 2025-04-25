<?php

namespace SchmidtWebmedia\MaskExportToContentBlocks\Command;

use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(tags: [
    [
        'name' => 'console.command',
        'command' => 'mask-export-to-content-blocks:migrate',
        'description' => 'Migrates migration JSON to ContentBlocks.',
        'schedulable' => false,
    ],
])]
class MigrateJsonCommand extends Command
{
    private string $ttContentTable = 'tt_content';
    private string $extensionKey = 'mask_export_to_content_blocks';

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln('<info>Starting of migration</info>');
        $migrationJson = $this->getMigrationJson();

        $maskElements = $this->getAllMaskElements();

        foreach ($maskElements as $maskElement) {
            $queryBuilder = $this->getQueryBuilder($this->ttContentTable);
            $migrationRecord = $migrationJson[$maskElement['CType']] ?? null;

            if($migrationRecord['contentBlock'] === '' || $migrationRecord['contentBlock'] === null) {
                $output->writeln('<info>'.$maskElement['CType'].' will be skipped because of missing ContentBlock</info>');
                continue;
            }
            if($migrationRecord !== null) {
                $queryBuilder->update($this->ttContentTable)
                    ->where(
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($maskElement['uid']))
                    );
                if(count($migrationRecord['fields'] ?? []) > 0) {
                    foreach ($migrationRecord['fields'] as $field) {
                        $value = $maskElement[$field['mask']];
                        $fieldType = $field['type'] ?? null;
                        if($fieldType === 'string') {
                            $value = $value === null ? '' : $value;
                        } else if($fieldType === 'integer') {
                            $value = $value === null ? 0 : $value;
                        }

                        $queryBuilder->set($field['contentBlock'], $value);
                        if($fieldType === 'file') {
                            $this->updateSysFileReference($maskElement['pid'], $maskElement['uid'], $field['contentBlock'], null);
                        }

                        if(array_key_exists('table', $field)) {
                            $this->updateForeignTable($maskElement['uid'], $field);
                        }
                    }
                }

                $queryBuilder->set('CType', $migrationRecord['contentBlock']);
                $queryBuilder->executeStatement();

                $this->updatePermissions($migrationRecord['mask'], $migrationRecord['contentBlock'], $migrationRecord['fields'] ?? [], false);


            }
        }


        return Command::SUCCESS;
    }

    public function updateNecessary(): bool {
        $maskElements = $this->getAllMaskElements();
        return count($maskElements) > 0;
    }

    private function getMigrationJson() : array {
        $path = Environment::getPublicPath() . '/fileadmin/'.$this->extensionKey.'/migration.json';
        return json_decode(file_get_contents(GeneralUtility::getFileAbsFileName($path)), true);
    }

    private function getAllMaskElements() : array {
        $migrationJson = $this->getMigrationJson();
        $migrationKeys = array_keys($migrationJson);

        $queryBuilder = $this->getQueryBuilder($this->ttContentTable);
        return $queryBuilder->select('*')
            ->from($this->ttContentTable)
            ->where(
                $queryBuilder->expr()->in('CType', $queryBuilder->createNamedParameter($migrationKeys, ArrayParameterType::STRING))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function updateSysFileReference(int $pid, int $uid, string $fieldName, ?string $tableName) : void {
        $queryBuilder = $this->getQueryBuilder('sys_file_reference');
        $queryBuilder->update('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid)),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($uid))
            )
            ->set('fieldname', $fieldName);

        if($tableName !== null) {
            $queryBuilder->set('tablenames', $tableName);
        }

        $queryBuilder->executeStatement();
    }

    private function updatePermissions(string $mask, string $contentBlock, array $fields, bool $asTable) : void {
        $queryBuilder = $this->getQueryBuilder('be_groups');

        if($asTable) {
            $beGroups = $queryBuilder->select('*')
                ->from('be_groups')
                ->where(
                    $queryBuilder->expr()->like('tables_select', $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards($mask) . '%'
                    ))
                )->orWhere(
                    $queryBuilder->expr()->like('tables_modify', $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards($mask) . '%'
                    ))
                )
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($beGroups as $group) {
                $group['tables_select'] = str_replace($mask, $contentBlock, $group['tables_select']);
                $group['tables_modify'] = str_replace($mask, $contentBlock, $group['tables_modify']);

                $queryBuilder->update('be_groups')
                    ->where(
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($group['uid']))
                    )
                    ->set('tables_select', $group['tables_select'])
                    ->set('tables_modify', $group['tables_modify'])
                    ->executeStatement();
            }
        } else {
            $beGroups = $queryBuilder->select('*')
                ->from('be_groups')
                ->where(
                    $queryBuilder->expr()->like('explicit_allowdeny', $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards($mask). '%'
                    ))
                )->orWhere(
                    $queryBuilder->expr()->like('non_exclude_fields', $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards($mask) . '%'
                    ))
                )
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($beGroups as $group) {
                $group['explicit_allowdeny'] = str_replace($mask, $contentBlock, $group['explicit_allowdeny']);
                foreach ($fields as $field) {
                    $group['non_exclude_fields'] = str_replace($field['mask'], $field['contentBlock'], $group['non_exclude_fields']);
                }

                $queryBuilder->update('be_groups')
                    ->where(
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($group['uid']))
                    )
                    ->set('explicit_allowdeny', $group['explicit_allowdeny'])
                    ->set('non_exclude_fields', $group['non_exclude_fields'])
                    ->executeStatement();
            }
        }
    }

    private function updateForeignTable(int $uid, array $migrationRecord) : void {
        $maskQb = $this->getQueryBuilder($migrationRecord['mask']);
        $cbQb = $this->getQueryBuilder($migrationRecord['contentBlock']);

        $oldRecords = $maskQb
            ->select('*')
            ->from($migrationRecord['mask'])
            ->where(
                $maskQb->expr()->eq('parentid', $maskQb->createNamedParameter($uid))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($oldRecords as $oldRecord) {
            $newRecord = $oldRecord;
            $newRecord['foreign_table_parent_uid'] = $oldRecord['parentid'];
            foreach ($migrationRecord['table']['fields'] as $field) {
                if(!($field['ignore'] ?? false)) {
                    $newValue = $oldRecord[$field['mask']];
                    if(($field['remapping'] ?? null) !== null) {
                        if(array_key_exists($oldRecord[$field['mask']], $field['remapping'])) {
                            $newValue = $field['remapping'][$oldRecord[$field['mask']]];
                        }
                    }
                    $newRecord[$field['contentBlock']] = $newValue;

                    $newRecord['l10n_diffsource'] = str_replace($oldRecord[$field['mask']], $newRecord[$field['contentBlock']], $oldRecord['l10n_diffsource']);

                    if(($field['type'] ?? null) === 'file') {
                        $this->updateSysFileReference($oldRecord['pid'], $oldRecord['uid'], $field['contentBlock'], $migrationRecord['contentBlock']);
                    }
                }

                unset($newRecord[$field['mask']]);
            }

            $columns = $cbQb->getConnection()->createSchemaManager()->listTableColumns($migrationRecord['contentBlock']);
            $allowedColumns = array_keys($columns);

            foreach (array_keys($newRecord) as $column) {
                if(!in_array($column, $allowedColumns)) {
                    unset($newRecord[$column]);
                }
            }

            $cbQb->insert($migrationRecord['contentBlock'])->values($newRecord)->executeStatement();

            $fields = array_filter($migrationRecord['table']['fields'] ?? [], function($field) {
                return !($field['ignore'] ?? false);
            });

            $this->updatePermissions($migrationRecord['mask'], $migrationRecord['contentBlock'], $fields, false);
            $this->updatePermissions($migrationRecord['mask'], $migrationRecord['contentBlock'], $fields, true);
        }
    }
    private function getQueryBuilder(string $table) : QueryBuilder {
        /** @var ConnectionPool $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class);
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $connection->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder;
    }

    private function validateMigrationJson(array $migrationJson) : bool {
        foreach ($migrationJson as $item) {
            if(($item['contentBlock'] ?? '') === '') {
                return false;
            }
        }

        return true;
    }
}
