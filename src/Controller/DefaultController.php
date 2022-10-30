<?php

namespace App\Controller;

use App\Form\SearchFormType;
use App\Service\DiscogsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('default/index.html.twig', [
            'pageTitle' => 'Ta Vinylothèque',
        ]);
    }

    #[Route('/search', name: 'app_search')]
    public function search(Request $request, DiscogsService $discogsService): Response
    {
        $artist = null;
        $album = null;

        $form = $this->createForm(SearchFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $artist = $discogsService->getArtist($form->get('artist')->getData());
            if ($artist && !empty($form->get('album')->getData())) {
                $album = $discogsService->getArtistAlbum($artist['name'], $form->get('album')->getData());
            }
        }

        return $this->renderForm('default/search.html.twig', [
            'pageTitle' => 'Discogs API Search Engine',
            'form' => $form,
            'artist' => $artist,
            'album' => $album,
        ]);
    }
}
