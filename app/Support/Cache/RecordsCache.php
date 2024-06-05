<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;


class RecordsCache
{
    protected static $cacheKey = 'Maranatha/Records';
    public static function getRecord(string $recordName, array $params): array|null
    {
        $hash = md5(json_encode($params));
        $cachedValue = Cache::store('file')->get(RecordsCache::$cacheKey . '/' . $recordName . '/' . $hash);

        if ($cachedValue){
            return json_decode($cachedValue, true);
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
        $data = json_encode($data);
        Cache::store('file')->put(RecordsCache::$cacheKey . '/' . $recordName . '/' . $hash, $data);
    }


    public static function clearAll(): void
    {
        Cache::flush();
    }
}
