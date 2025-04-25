<?php
namespace SchmidtWebmedia\MaskExportToContentBlocks\Command;


use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(tags: [
    [
        'name' => 'console.command',
        'command' => 'mask-export-to-content-blocks:prepare',
        'description' => 'Migrates MASK Json to Migration JSON.',
        'schedulable' => false,
    ],
])]
class PrepareMigrationJsonCommand extends Command
{

    private string $extensionKey = 'mask_export_to_content_blocks';
    private array $maskJson = [];

    private ?string $extensionName = null;

    protected function configure() : void {
        $this->addOption(
            'path',
            '',
            InputOption::VALUE_REQUIRED,
            'Path to mask.json from mask_export extension'
        );
    }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $path = $input->getOption('path');

        $output->writeln('<info>Staring Migrating MASK JSON</info>');

        $output->writeln("<info>Reading mask.json from $path</info>");

        if(!file_exists($path)) {
            $output->writeln("<error>$path not found</error>");
            return Command::FAILURE;
        }

        $maskJson = file_get_contents($path);
        $this->maskJson = json_decode($maskJson, true);

        $newData = $this->transformData();

        $outputPath = GeneralUtility::getFileAbsFileName('EXT:'.$this->extensionKey.'/Resources/Private/Update/migration.json');

        GeneralUtility::mkdir_deep(dirname($outputPath));

        file_put_contents($outputPath, json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $output->writeln('<info>Migration completed</info>');

        return Command::SUCCESS;
    }

    private function transformData() : array {
        $output = [];

        $configuration = $this->maskJson['tables']['mask_export']['elements']['configuration'];
        $tt_content = $this->maskJson['tables']['tt_content'];

        $this->extensionName = $configuration['label'];
        $contentElements = $configuration['columns'];

        foreach ($contentElements as $contentElement) {
            $maskCe = $this->extensionName.'_'.$contentElement;

            $fields = $this->transformFields($tt_content['tca'], $tt_content['elements'][$contentElement]['columns']);

            $output[$maskCe] = [
                'contentBlock' => '',
                'mask' => $maskCe,
                'fields' => $fields
            ];
        }

        return $output;
    }

    private function transformFields(array $tca, array $oldFields) : array {
        $output = [];

        foreach ($oldFields as $oldField) {
            $type = $tca[$oldField]['type'] ?? null;
            $newField = [];
            if($type === 'inline') {
                $newField['table']['fields'] = $this->transformTable($oldField);
            }
            $newField = array_merge([
                'mask' => str_replace('tx_mask_', 'tx_'.$this->extensionName.'_', $oldField),
                'contentBlock' => '',
                'ignore' => false
            ], $newField);

            if($type !== null) {
                $newField['type'] = $type;

                if($type === 'select') {
                    $newField['remapping'] = new stdClass();
                }

            }

            $output[] = $newField;

        }

        return $output;
    }

    private function transformTable(string $fieldName) : array {
        $table = $this->maskJson['tables'][$fieldName];

       return $this->transformFields($table['tca'], array_keys($table['tca']));
    }
}
