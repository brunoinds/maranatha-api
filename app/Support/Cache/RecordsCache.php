<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;


class RecordsCache
{
    protected static $cacheKey = 'Maranatha/Records';
    public static function getRecord(string $recordName, array $params): array|null
    {
        $hash = md5(json_encode($params));
        $cachedValue = Cache::store('file')->get(RecordsCache::$cacheKey);

        if ($cachedValue){
            $cache = json_decode($cachedValue, true);
            if (isset($cache[$recordName]) && isset($cache[$recordName][$hash])){
                return $cache[$recordName][$hash];
            }
        }

        return null;
    }

    public static function isAvailable(string $recordName, array $params): bool
    {
        return RecordsCache::getRecord($recordName, $params) !== null;
    }

    public static function storeRecord(string $recordName, array $params, array $data): void
    {
        $hash = md5(json_encode($params));

        $cachedValue = Cache::store('file')->get(RecordsCache::$cacheKey);

        $cache = [];

        if ($cachedValue){
            $cache = json_decode($cachedValue, true);
        }

        if (isset($cache[$recordName])){
            $cache[$recordName] = [];
        }

        $cache[$recordName][$hash] = $data;
        $data = json_encode($cache);
        Cache::store('file')->put(RecordsCache::$cacheKey, $data);
    }


    public static function clearAll(): void
    {
        Cache::store('file')->forget(RecordsCache::$cacheKey);
    }
}
