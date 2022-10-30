<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Category;
use App\Entity\UserAlbum;
use App\Form\SearchFormType;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserAlbumRepository;
use App\Repository\UserRepository;
use Discogs\DiscogsClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(UserAlbumRepository $userAlbumRepository, AlbumRepository $albumRepository, UserRepository $userRepository): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                $users = $userRepository->findAll();
                $adminUsers = array_filter($users, function ($user) {
                    return $user->getId() !== $this->getUser()->getId();
                });

                return $this->render('default/admin.html.twig', [
                    'pageTitle' => 'Admin Vinylothèque',
                    'users' => $adminUsers,
                ]);
            } else {
                $albums = [];
                $userAlbums = $userAlbumRepository->findBy(['user' => $this->getUser()]);
                foreach ($userAlbums as $album) {
                    $albums[] = $albumRepository->findOneBy(['id' => $album->getAlbum()->getId()]);
                }

                return $this->render('default/index.html.twig', [
                    'pageTitle' => 'Vinylothèque',
                    'albums' => $albums,
                ]);
            }
        } else {
            return $this->render('default/index.html.twig', [
                'pageTitle' => 'Vinylothèque',
                'albums' => [],
            ]);
        }
    }

    #[Route('/search', name: 'app_search')]
    public function search(Request $request, DiscogsClient $discogs, ArtistRepository $artistRepository, AlbumRepository $albumRepository, CategoryRepository $categoryRepository, UserAlbumRepository $userAlbumRepository, EntityManagerInterface $manager): Response
    {
        $form = $this->createForm(SearchFormType::class);
        $form->handleRequest($request);

        $artist = null;
        $artistAlbum = null;
        $artistName = null;
        $albumName = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $artistName = $form->get('artist')->getData();
            $albumName = $form->get('album')->getData();
        }

        $artists = $discogs->search(['q' => ucwords($artistName), 'type' => 'artist']);
        foreach ($artists['results'] as $result) {
            if ($result['title'] === ucwords($artistName)) {
                $artist = $result;
            }
        }

        if ($artist) {
            $artistName = $artist['title'];

            $artistExists = $artistRepository->findOneBy(['discogsId' => $artist['id']]);
            if (!$artistExists) {
                $newArtist = new Artist();
                $newArtist->setDiscogsId($artist['id'])
                    ->setName($artistName)
                    ->setCover($artist['cover_image']);
                $manager->persist($newArtist);
                $manager->flush();

                $artistExists = $newArtist;
            }

            if ($albumName) {
                $albums = $discogs->search(['release_title' => ucwords($albumName), 'artist' => ucwords($artistName), 'format' => 'Vinyl'])->toArray();
                usort($albums['results'], function ($item1, $item2) {
                    if (isset($item1['year']) && isset($item2['year'])) {
                        return $item1['year'] <= $item2['year'];
                    } else {
                        return false;
                    }
                });
                foreach ($albums['results'] as $result) {
                    $ret = explode(' - ', $result['title']);
                    if (isset($ret[1])) {
                        $albumTitle = strtolower($ret[1]);
                    } else {
                        $albumTitle = strtolower($ret[0]);
                    }
                    if ($albumTitle === strtolower($albumName) && isset($result['year'])) {
                        $artistAlbum = $result;
                    }
                }

                if ($artistAlbum) {
                    $albumExists = $albumRepository->findBy(['discogsId' => $artistAlbum['id']]);
                    if (!$albumExists) {
                        $categoryExists = $categoryRepository->findBy(['name' => $artistAlbum['genre'][0]]);
                        if (!$categoryExists) {
                            $newCategory = new Category();
                            $newCategory->setName($artistAlbum['genre'][0])
                                ->setCover($artistAlbum['cover_image']);
                            $manager->persist($newCategory);
                        } else {
                            $newCategory = $categoryExists[0];
                        }

                        $newAlbum = new Album();
                        $newAlbum->setArtist($artistExists)
                            ->setCategory($newCategory)
                            ->setDiscogsId($artistAlbum['id'])
                            ->setTitle(ucwords($albumTitle))
                            ->setCover($artistAlbum['cover_image'])
                            ->setYear($artistAlbum['year']);
                        $manager->persist($newAlbum);

                        $userAlbumExists = $userAlbumRepository->findOneBy(['user' => $this->getUser(), 'album' => $newAlbum]);
                        if (!$userAlbumExists) {
                            $newUserAlbum = new UserAlbum();
                            $newUserAlbum->setUser($this->getUser())
                                ->setAlbum($newAlbum)
                                ->setPlayed(0)
                                ->setFavorite(false);
                            $manager->persist($newUserAlbum);

                        }
                    }

                    $manager->flush();
                }
            } else {
                $artistAlbum = $discogs->search(['artist' => ucwords($artistName), 'format' => 'Vinyl'])->toArray();
                usort($artistAlbum['results'], function ($item1, $item2) {
                    if (isset($item1['year']) && isset($item2['year'])) {
                        return $item1['year'] <= $item2['year'];
                    } else {
                        return false;
                    }
                });
            }
        }

        return $this->renderForm('default/search.html.twig', [
            'pageTitle' => 'Recherche Discogs API',
            'form' => $form,
            'artist' => $artist,
            'album' => $artistAlbum,
        ]);
    }
}
