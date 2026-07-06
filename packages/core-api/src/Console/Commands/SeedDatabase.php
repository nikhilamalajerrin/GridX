<?php

namespace GridX\Console\Commands;

use GridX\Support\Utils;
use Illuminate\Console\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

class SeedDatabase extends Command
{
    protected $signature   = 'gridx:seed {--class=}';
    protected $description = 'Run GridX seeders';

    public function handle(): int
    {
        $seederClass = $this->option('class');

        if ($seederClass) {
            return $this->runSeeder("GridX\\Seeders\\{$seederClass}");
        }

        $io = new SymfonyStyle($this->input, $this->output);

        $this->components->info('Running GridX core seeder…');
        $this->runSeeder('GridX\\Seeders\\GridXSeeder');

        $extensionSeeders = Utils::getSeedersFromExtensions();

        if (empty($extensionSeeders)) {
            $this->components->warn('No extension seeders found.');

            return self::SUCCESS;
        }

        $io->newLine();
        $io->text('Running extension seeders:');
        $bar = $io->createProgressBar(count($extensionSeeders));
        $bar->setFormat(' %current%/%max% [%bar%] %message%');
        $bar->start();

        foreach ($extensionSeeders as $seed) {
            $bar->setMessage($seed['class']);
            require_once $seed['path'];
            (new $seed['class']())->run();
            $bar->advance();
        }

        $bar->finish();
        $io->newLine(2);
        $this->components->info('All seeders completed.');

        return self::SUCCESS;
    }

    protected function runSeeder(string $class): int
    {
        return (int) $this->call('db:seed', [
            '--class' => $class,
            '--force' => true,
        ]);
    }
}
