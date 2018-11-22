<?php

namespace Pho\Stream\Model;

use Pho\Stream\RedisCommand;
use Predis\Client;

class FeedModel
{
    private $client;
    private $redisCommand;

    public function __construct(Client $client, RedisCommand $redisCommand)
    {
        $this->client = $client;
        $this->redisCommand = $redisCommand;
    }

    public function addActivity($userId, $actor, $verb, $object, $text)
    {
        $activityData = [
            'actor' => $actor,
            'verb' => $verb,
            'object' => $object,
            'text' => $text,
        ];
        $id = $this->redisCommand->xadd("user_{$userId}", '*', $activityData);

        $followers = $this->client->smembers("follower_user_{$userId}");
        foreach ($followers as $follower) {
            $this->redisCommand->xadd($follower, $id, $activityData);
        }

        return $id;
    }

    public function feedExists($feed)
    {
        return (bool) $this->client->exists($feed);
    }

    public function follow($followerFeed, $followeeFeed)
    {
        if ($this->client->sadd("followee_{$followerFeed}", $followeeFeed) == 0) {
            return false;
        }
        if ($this->client->sadd("follower_{$followeeFeed}", $followerFeed) == 0) {
            return false;
        }

        return true;
    }

    public function get($feedSlug, $userId, $count = 25, $offset = 0)
    {
        if ($count === null) {
            $count = 25;
        }
        $count += $offset;
        $stream = "{$feedSlug}_{$userId}";
        $response = $this->redisCommand->xrevrange($stream, '+', '-', $count);

        $feed = [];

        $skipCount = 0;

        foreach ($response as $id => $dictionary) {

            if ($skipCount !== $offset) {
                $skipCount++;
                continue;
            }

            $feed[] = [
                'id' => $id,
            ] + $dictionary;
        }

        return $feed;
    }
}