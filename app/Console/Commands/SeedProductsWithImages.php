<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\ProductsWithImagesSeeder;

class SeedProductsWithImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:products-with-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère des produits avec des images réelles depuis Unsplash';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌱 Début de la génération de produits avec images...');
        $this->newLine();

        $seeder = new ProductsWithImagesSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('✅ Génération terminée avec succès!');
        $this->info('📊 Vous pouvez maintenant consulter vos produits avec images dans le catalogue.');

        return Command::SUCCESS;
    }
}
