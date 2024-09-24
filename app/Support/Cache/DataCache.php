<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;


class DataCache
{
    protected static $cacheKey = 'Maranatha/Data';
    public static function getRecord(string $recordName, array $params): array|null
    {
        $hash = md5($recordName . json_encode($params));
        $cachedValue = Cache::store('file')->get($hash);

        if ($cachedValue){
            return json_decode($cachedValue, true);
        }

        return null;
    }

    public static function isAvailable(string $recordName, array $params): bool
    {
        return self::getRecord($recordName, $params) !== null;
    }

    public static function storeRecord(string $recordName, array $params, array $data): void
    {
        $hash = md5($recordName . json_encode($params));
        $data = json_encode($data);
        Cache::store('file')->put($hash, $data);
    }

    public static function clearRecord(string $recordName, array $params): void
    {
        $hash = md5($recordName . json_encode($params));
        Cache::store('file')->forget($hash);
    }
}
