<?php

namespace App\Modules\User\Command;

use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Creates a new user with database roles'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('email', null,
                InputOption::VALUE_REQUIRED,
                'User email')
            ->addOption('password', null,
                InputOption::VALUE_REQUIRED,
                'User password')
            ->addOption('role', null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'User role name(s) from database (can specify multiple roles)',
                [])
            ->addOption('list-roles', null,
                InputOption::VALUE_NONE,
                'List all available roles');
    }
    
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        
        // اگر فقط می‌خواد role ها رو ببینه
        if ($input->getOption('list-roles')) {
            $this->listAvailableRoles($io);
            return Command::SUCCESS;
        }
        
        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $roleNames = $input->getOption('role');
        
        // Validation
        if (!$email) {
            $io->error('Email is required. Use --email option.');
            return Command::FAILURE;
        }
        
        if (!$password) {
            $io->error('Password is required. Use --password option.');
            return Command::FAILURE;
        }
        
        // بررسی اینکه user با این email وجود نداشته باشه
        $existingUser = $this->entityManager->getRepository(UserEntity::class)
            ->findOneBy(['email' => $email]);
        
        if ($existingUser) {
            $io->error(sprintf('User with email "%s" already exists.', $email));
            return Command::FAILURE;
        }
        
        // ایجاد user
        $user = new UserEntity();
        $user->setEmail($email);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        
        // اضافه کردن role ها از database
        if (!empty($roleNames)) {
            foreach ($roleNames as $roleName) {
                $role = $this->entityManager->getRepository(Role::class)
                    ->findOneBy(['name' => $roleName]);
                
                if ($role) {
                    $user->addUserRole($role);
                    $io->text(sprintf('Added role: %s', $roleName));
                } else {
                    $io->warning(sprintf('Role "%s" not found in database. Skipping...', $roleName));
                }
            }
        } else {
            $io->note('No roles specified. User will have default ROLE_USER only.');
        }
        
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $io->success(sprintf('User "%s" created successfully!', $email));
            
            // نمایش اطلاعات user
            $io->section('User Details:');
            $io->table(
                ['Property', 'Value'],
                [
                    ['ID', $user->getId()],
                    ['Email', $user->getEmail()],
                    ['Roles', implode(', ', $user->getRoles())],
                    ['Active', $user->isActive() ? 'Yes' : 'No'],
                    ['Created At', $user->getCreatedAt()->format('Y-m-d H:i:s')],
                ]
            );
            
        } catch (\Exception $e) {
            $io->error(sprintf('Error creating user: %s', $e->getMessage()));
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    private function listAvailableRoles(SymfonyStyle $io): void
    {
        $roles = $this->entityManager->getRepository(Role::class)->findAll();
        
        if (empty($roles)) {
            $io->warning('No roles found in database.');
            $io->note('Create roles first using role management commands or web interface.');
            return;
        }
        
        $io->title('Available Roles:');
        
        $tableData = [];
        foreach ($roles as $role) {
            $permissionCount = $role->getPermissions()->count();
            $tableData[] = [
                $role->getId(),
                $role->getName(),
                $permissionCount . ' permissions'
            ];
        }
        
        $io->table(['ID', 'Role Name', 'Permissions'], $tableData);
        
        $io->note([
            'Usage examples:',
            'php bin/console app:create-user --email=admin@example.com --password=secret --role=admin',
            'php bin/console app:create-user --email=user@example.com --password=secret --role=editor --role=viewer',
        ]);
    }
}