<?php

namespace App\Support\Search\GoogleImages;
use DevDojo\GoogleImageSearch\ImageSearch;

ImageSearch::config()->apiKey(env('GOOGLE_CUSTOM_SEARCH_API_KEY'));
ImageSearch::config()->cx(env('GOOGLE_CUSTOM_SEARCH_CX'));


class GoogleImageSearch{
    public static function search(string $searchTerm): array{
        $results = collect(ImageSearch::search($searchTerm)['items']);

        $results = $results->map(function ($result) {
            return $result['image']['thumbnailLink'];
        });

        return $results->toArray();
    }
}
