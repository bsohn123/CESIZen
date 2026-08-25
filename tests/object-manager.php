<?php

// Fournit l'EntityManager à l'extension Doctrine de PHPStan, afin qu'il
// connaisse la cartographie réelle des entités (types de colonnes, champs
// générés) plutôt que de déduire les types des seules propriétés PHP.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
