<?php

namespace App\Modules\User\Command;

use App\Modules\User\Security\DatabaseRoleHierarchy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'user:role-hierarchy:cache',
    description: 'Manage user role hierarchy cache',
)]
class RoleHierarchyCacheCommand extends Command
{
    private DatabaseRoleHierarchy $roleHierarchy;
    
    public function __construct(
        DatabaseRoleHierarchy $roleHierarchy
    ) {
        $this->roleHierarchy = $roleHierarchy;
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('warm-up', 'w',
                InputOption::VALUE_NONE,
                'Warm up the cache')
            ->addOption('clear', 'c',
                InputOption::VALUE_NONE,
                'Clear the cache')
            ->addOption('show', 's',
                InputOption::VALUE_NONE,
                'Show current hierarchy');
    }
    
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        
        if ($input->getOption('clear')) {
            $this->roleHierarchy->invalidateCache();
            $io->success('Role hierarchy cache cleared!');
        }
        
        if ($input->getOption('warm-up')) {
            $io->info('Warming up role hierarchy cache...');
            $this->roleHierarchy->warmUpCache();
            $io->success('Role hierarchy cache warmed up successfully!');
        }
        
        if ($input->getOption('show')) {
            $hierarchy = $this->roleHierarchy->getRoleHierarchy();
            $io->section('Current Role Hierarchy:');
            
            if (empty($hierarchy)) {
                $io->note('No role hierarchy found.');
            } else {
                foreach ($hierarchy as $parent => $children) {
                    $io->writeln(sprintf('<info>%s</info> -> %s',
                        $parent, implode(', ',
                            $children)));
                }
            }
        }
        
        if (!$input->getOption('clear') && !$input->getOption('warm-up') && !$input->getOption('show')) {
            $io->note('Use --help to see available options');
        }
        
        return Command::SUCCESS;
    }
}