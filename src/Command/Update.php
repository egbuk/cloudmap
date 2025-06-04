<?php

namespace App\Command;

use App\Service\CloudUpdateService;
use Brick\Geo\Exception\CoordinateSystemException;
use Brick\Geo\Exception\GeometryEngineException;
use Brick\Geo\Exception\GeometryException;
use Brick\Geo\Exception\InvalidGeometryException;
use ImagickException;
use ImagickPixelException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:update')]
class Update extends Command
{
    public function __construct(private readonly CloudUpdateService $service)
    {
        parent::__construct();
    }

    /**
     * @throws ImagickException
     * @throws CoordinateSystemException
     * @throws GeometryEngineException
     * @throws ImagickPixelException
     * @throws InvalidGeometryException
     * @throws InvalidArgumentException
     * @throws GeometryException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Running...');
        ini_set('memory_limit', '4G');
        $this->service->update(new ProgressBar($output));
        $output->writeln('Done.');
        return Command::SUCCESS;
    }
}
