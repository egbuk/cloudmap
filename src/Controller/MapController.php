<?php

namespace App\Controller;

use App\Repository\TileRepository;
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
        private readonly Filesystem $filesystem
    ) {}

    #[Route('/', methods: ['GET'])]
    public function map(): Response
    {
        return $this->render('map.html.twig');
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/clouds', methods: ['GET'])]
    public function clouds(#[MapQueryParameter] int $x,
                           #[MapQueryParameter] int $y,
                           #[MapQueryParameter] int $z,
                           TileRepository $tileRepository): Response
    {
        return new Response($tileRepository->get(TilePosition::xyzFlip($x, $y, $z)), 200, [
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
                    'maxZoom' => 14
                ]
            ]
        ]));
    }
}
