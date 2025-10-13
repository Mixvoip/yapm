<?php

/**
 * @author bsteffan
 * @since 2025-09-25
 */

namespace App\Command\CleanUp;

use App\Message\PartialAccessCleanUpMessage;
use App\Service\CleanUp\PartialAccessCleaner;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:clean-up:partial-access',
    description: 'Clean up partial access on all vaults and groups.'
)]
class PartialAccessCleanUpCommand extends Command
{
    /**
     * @param  PartialAccessCleaner  $partialAccessCleaner
     * @param  MessageBusInterface  $bus
     */
    public function __construct(
        private readonly PartialAccessCleaner $partialAccessCleaner,
        private readonly MessageBusInterface $bus
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setHelp("This command cleans up partial access on all vaults and groups.")
             ->addOption(
                 "detached",
                 "D",
                 InputOption::VALUE_NONE,
                 "Run the command detached in background."
             );
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     *
     * @return int
     * @throws \Doctrine\DBAL\Exception
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption("detached")) {
            $message = new PartialAccessCleanUpMessage();
            $this->bus->dispatch($message);
            $io->success("Partial access clean up message dispatched.");

            return Command::SUCCESS;
        }

        try {
            $deleted = $this->partialAccessCleaner->cleanUp();
        } catch (Exception $e) {
            $io->error("Failed to clean up partial access: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success("Partial access clean up completed successfully.");
        $io->info("Deleted $deleted partial access entries.");

        return Command::SUCCESS;
    }
}
