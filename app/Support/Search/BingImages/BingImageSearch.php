<?php

namespace App\Support\Search\BingImages;

use DOMDocument;


class BingImageSearch{
    public static function search(string $searchTerm): array{
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'
            ]
        ]);


        $query = [
            'q' => $searchTerm,
            'qs' => 'n',
            'form' => 'QBIDMH',
            'sp' => '-1',
            'lq' => '0',
            'pq' => strtolower($searchTerm),
            'sc' => '1-25',
            'cvid' => '4AA65572613E4894816F750AB2D1DDB8',
            'ghsh' => '0',
            'ghacc' => '0',
            'first' => '1',
            'cw' => '1177',
            'ch' => '788'
        ];

        $url = 'https://www.bing.com/images/search?' . http_build_query($query);

        $result = file_get_contents($url, false, $context);

        $dom = new DOMDocument();
        @$dom->loadHTML($result);
        $items = $dom->getElementsByTagName('img');

        $results = [];

        foreach ($items as $item) {
            if (str_contains($item->getAttribute('class'), 'mimg')) {
                if (trim($item->getAttribute('src')) !== '') {
                    $results[] = $item->getAttribute('src');
                }
            }
        }

        return $results;
    }


}
