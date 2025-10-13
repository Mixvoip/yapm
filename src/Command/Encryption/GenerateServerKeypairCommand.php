<?php

/**
 * @author bsteffan
 * @since 2025-06-26
 */

namespace App\Command\Encryption;

use App\Service\Encryption\EncryptionService;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:encryption:generate-server-keypair',
    description: 'Generate server keypair for encryption'
)]
class GenerateServerKeypairCommand extends Command
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setHelp('This command generates a server keypair for encryption.');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $keypair = EncryptionService::generateServerKeypair();

            $io->title('Server Keypair Generated Successfully');

            $io->section('Environment Variables');
            $io->text('Add these to your .env file:');
            $io->newLine();

            $io->text([
                'SERVER_PRIVATE_KEY=' . $keypair['privateKey'],
                'SERVER_PUBLIC_KEY=' . $keypair['publicKey'],
            ]);

            $io->newLine();
            $io->warning([
                'IMPORTANT SECURITY NOTES:',
                '1. Store the private key securely - never commit it to version control',
                '2. Consider using environment-specific key management',
                '3. Backup these keys - losing them means losing access to all encrypted data',
                '4. The public key can be safely shared with clients',
            ]);

            $io->success('Server keypair generated successfully!');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Failed to generate server keypair: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
