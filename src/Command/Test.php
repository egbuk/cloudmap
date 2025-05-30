<?php

namespace App\Command;

use App\Service\CloudSearchService;
use App\Service\FootageService;
use Brick\Geo\Exception\CoordinateSystemException;
use Brick\Geo\Exception\GeometryEngineException;
use Brick\Geo\Exception\GeometryIOException;
use Brick\Geo\Exception\InvalidGeometryException;
use Brick\Geo\IO\GeoJSONWriter;
use ImagickException;
use ImagickPixelException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:test')]
class Test extends Command
{
    public function __construct(
        private readonly FootageService $footageService,
        private readonly CloudSearchService $cloudSearchService,
        private readonly GeoJSONWriter $geoJSONWriter
    )
    {
        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws CoordinateSystemException
     * @throws GeometryEngineException
     * @throws ImagickException
     * @throws ImagickPixelException
     * @throws InvalidGeometryException
     * @throws GeometryIOException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $footage = $this->footageService->get();
        ini_set('memory_limit', '4G');
        $output->write($this->geoJSONWriter->write($this->cloudSearchService->process($footage)));
        $this->footageService->clear($footage);
        return Command::SUCCESS;
    }
}
