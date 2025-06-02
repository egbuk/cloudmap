<?php

namespace App\Controller;

use App\Repository\TileRepository;
use DateTime;
use Exception;
use HeyMoon\VectorTileDataProvider\Entity\TilePosition;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends AbstractController
{
    public function __construct(
        private readonly string $stylePath,
        private readonly string $mapTilerToken,
        private readonly Filesystem $filesystem,
        private readonly TileRepository $tileRepository
    ) {}

    /**
     * @throws Exception
     */
    #[Route('/', methods: ['GET'])]
    public function map(): Response
    {
        $time = $this->tileRepository->getCurrentTime();
        return $this->render('map.html.twig', [
            'time' => (new DateTime($time))->getTimestamp(),
            'display' => $time
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/clouds', methods: ['GET'])]
    public function clouds(#[MapQueryParameter] int $x,
                           #[MapQueryParameter] int $y,
                           #[MapQueryParameter] int $z): Response
    {
        return new Response($this->tileRepository->get(TilePosition::xyzFlip($x, $y, $z)), 200, [
            'Content-Type' => 'application/x-protobuf',
            'Content-Encoding' => 'gzip',
            'Access-Control-Allow-Origin' => '*'
        ]);
    }

    #[Route('/style', methods: ['GET'])]
    public function style(Request $request): JsonResponse
    {
        return $this->json(array_merge(json_decode($this->filesystem->readFile($this->stylePath), true), [
            'glyphs' => "https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=$this->mapTilerToken",
            'sources' => [
                'contours' => [
                    'url' => "https://api.maptiler.com/tiles/contours-v2/tiles.json?key=$this->mapTilerToken",
                    'type' => 'vector'
                ],
                'maptiler_planet' => [
                    'url' => "https://api.maptiler.com/tiles/v3/tiles.json?key=$this->mapTilerToken",
                     'type' => 'vector'
                ],
                'outdoor' => [
                    'url' => "https://api.maptiler.com/tiles/outdoor/tiles.json?key=$this->mapTilerToken",
                    'type' => 'vector'
                ],
                'terrain-rgb' => [
                    'url' => "https://api.maptiler.com/tiles/terrain-rgb-v2/tiles.json?key=$this->mapTilerToken",
                    'type' => 'raster-dem'
                ],
                'clouds' => [
                    'type' => 'vector',
                    'tiles' => [
                        "{$request->getSchemeAndHttpHost()}/clouds?x={x}&y={y}&z={z}"
                    ],
                    'minZoom' => 0,
                    'maxZoom' => 22,
                    'attribution' => 'Contains modified <a href="https://www.eumetsat.int" target="_blank">EUMETSAT</a> data '.date('Y')
                ]
            ]
        ]));
    }
}
