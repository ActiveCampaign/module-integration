<?php
declare(strict_types=1);

namespace ActiveCampaign\Product\Console\Command;

use ActiveCampaign\Product\Model\ProductSync;
use ActiveCampaign\Product\Model\CatalogProductProvider;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BulkUpsertCommand extends Command
{
    private const OPTION_CONNECTION = 'connection';
    private const OPTION_STORE = 'store';

    private $state;
    private $storeManager;
    private $catalogProductProvider;

    public function __construct(State $state, StoreManagerInterface $storeManager, CatalogProductProvider $catalogProductProvider, ?string $name = null)
    {
        parent::__construct($name);
        $this->state = $state;
        $this->storeManager = $storeManager;
        $this->catalogProductProvider = $catalogProductProvider;
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
        }
    }

    protected function configure()
    {
        $this->setName('activecampaign:product:bulk-upsert')
            ->setDescription('Bulk upsert Magento catalog products to ActiveCampaign via GraphQL')
            ->addOption(
                self::OPTION_CONNECTION,
                null,
                InputOption::VALUE_OPTIONAL,
                'legacyConnectionId override'
            )
            ->addOption(
                self::OPTION_STORE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Store ID to export from'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $input->getOption(self::OPTION_CONNECTION);
        $legacyConnectionId = $connection !== null ? (int)$connection : null;
        $storeIdOpt = $input->getOption(self::OPTION_STORE);
        $storeId = $storeIdOpt !== null ? (int)$storeIdOpt : (int)($this->storeManager->getDefaultStoreView()->getId() ?? 0);

        $coreData = ObjectManager::getInstance()->get(\ActiveCampaign\Core\Helper\Data::class);
        $curl = ObjectManager::getInstance()->get(\ActiveCampaign\Core\Helper\Curl::class);
        if ($legacyConnectionId === null) {
            $legacyConnectionId = (int)($coreData->getConnectionId($storeId) ?? 0);
        }
        try {
            $connections = $curl->getAllConnections('GET', 'connections');
            $availableIds = [];
            if (!empty($connections['success']) && !empty($connections['data']['connections'])) {
                foreach ($connections['data']['connections'] as $conn) {
                    $availableIds[] = (int)$conn['id'];
                }
            }
            if ($legacyConnectionId && !in_array($legacyConnectionId, $availableIds, true)) {
                $output->writeln('<error>legacyConnectionId ' . $legacyConnectionId . ' no existe en esta cuenta.</error>');
                if ($availableIds) {
                    $output->writeln('<comment>IDs disponibles: ' . implode(',', $availableIds) . '</comment>');
                }
                return Cli::RETURN_FAILURE;
            }
        } catch (\Exception $e) {
        }

        $data = $this->catalogProductProvider->buildProducts($storeId);
        if (empty($data)) {
            $output->writeln('<comment>No products to export.</comment>');
            return Cli::RETURN_SUCCESS;
        }

        $productSync = ObjectManager::getInstance()->get(ProductSync::class);
        $result = $productSync->bulkUpsertProducts($data, $legacyConnectionId);
        if (!empty($result['success'])) {
            $output->writeln('<info>Bulk upsert success</info>');
            if (isset($result['data'])) {
                $output->writeln(json_encode($result['data']));
            }
            return Cli::RETURN_SUCCESS;
        }

        $status = $result['status'] ?? 0;
        $message = $result['message'] ?? 'Unknown error';
        $output->writeln('<error>Bulk upsert failed (' . $status . '): ' . $message . '</error>');
        
        return Cli::RETURN_FAILURE;
    }
}
