<?php

/**
 * @author bsteffan
 * @since 2025-10-10
 */

namespace App\Command;

use App\Domain\AppConstants;
use App\Entity\User;
use App\Service\Utility\Base64UrlHelper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create admin user'
)]
class CreateAdminUserCommand extends Command
{
    /**
     * @param  EntityManagerInterface  $entityManager
     */
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setHelp('This command creates an admin user.')
             ->addArgument(
                 "username",
                 InputArgument::REQUIRED,
                 "Display name of the user"
             )
             ->addArgument(
                 "email",
                 InputArgument::REQUIRED,
                 "Email of the user"
             );
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     *
     * @return int
     * @throws RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = trim($input->getArgument("username"));
        $email = trim($input->getArgument("email"));

        if (empty($username) || empty($email)) {
            $io->error("Username and email are required.");
            return Command::FAILURE;
        }

        $verificationToken = bin2hex(random_bytes(16));
        $user = new User()->setEmail($email)
                          ->setUsername($username)
                          ->setAdmin(true)
                          ->setCreatedBy("system")
                          ->setVerificationToken($verificationToken);

        $this->entityManager->persist($user);
        try {
            $this->entityManager->flush();
        } catch (Exception $e) {
            $io->error("Failed to create admin user: " . $e->getMessage());
            return Command::FAILURE;
        }

        $token = Base64UrlHelper::encode($verificationToken);
        $registrationLink = AppConstants::$frontendBaseUri . '/register/' . $token;

        $io->success(
            "Admin user created successfully.\nPlease complete registration using this link:\n$registrationLink"
        );

        return Command::SUCCESS;
    }
}
