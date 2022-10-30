<?php

namespace App\Service;

use Discogs\DiscogsClient;

class DiscogsService
{
    private $discogs;

    public function __construct(DiscogsClient $discogs)
    {
        $this->discogs = $discogs;
    }

    public function getArtist(string $artistName): array
    {
        $artist = [];
        $results = $this->discogs->search(['q' => ucwords($artistName), 'type' => 'artist']);
        foreach ($results['results'] as $result) {
            if ($result['title'] === ucwords($artistName) && !empty($result['cover_image'])) {
                $artist['discogsId'] = $result['id'];
                $artist['name'] = $result['title'];
                $artist['cover'] = $result['cover_image'];
            }
        }

        return $artist;
    }

    public function getArtistAlbum(string $artistName, string $albumName): array
    {
        $album = [];
        $results = $this->discogs->search(['release_title' => ucwords($albumName), 'artist' => ucwords($artistName), 'format' => 'Vinyl'])->toArray();
        usort($results['results'], function ($item1, $item2) {
            if (isset($item1['year']) && isset($item2['year'])) {
                return $item1['year'] <= $item2['year'];
            } else {
                return false;
            }
        });
        foreach ($results['results'] as $result) {
            $ret = explode(' - ', $result['title']);
            if (isset($ret[1])) {
                $albumTitle = strtolower($ret[1]);
            } else {
                $albumTitle = strtolower($ret[0]);
            }
            if ($albumTitle === strtolower($albumName) && !empty($result['year']) && !empty($result['cover_image'])) {
                $album = [
                    'discogsId' => $result['id'],
                    'title' => ucwords($albumTitle),
                    'genre' => $result['genre'],
                    'style' => $result['style'],
                    'cover' => $result['cover_image'],
                    'year' => $result['year'],
                ];
            }
        }

        return $album;
    }
}
